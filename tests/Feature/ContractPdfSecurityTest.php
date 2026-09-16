<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class ContractPdfSecurityTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_conditions_and_historical_price_do_not_depend_on_current_car_price(): void
    {
        $a = $this->tenant('history');
        $captured = null;
        View::composer('agence.contrats.pdf', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });
        $a['car']->update(['prix_jour' => 9999]);
        $this->actingAs($a['admin']);
        foreach ([true, false] as $linked) {
            if (! $linked) {
                $a['contract']->update(['reservation_id' => null]);
            }
            $this->get('/contrats/'.$a['contract']->id.'/pdf')->assertOk();
            $this->assertEquals(300, $captured['dailyPrice']);
            $this->assertStringContainsString('Tout dommage ou incident', view('agence.contrats.pdf', $captured)->render());
        }
    }

    public function test_real_images_and_long_notes_render_as_a_multi_page_pdf(): void
    {
        Storage::fake('public');
        $a = $this->tenant('images');
        // Generate an actual RGB PNG without requiring a manually supplied asset or GD.
        $chunk = static fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 32, 32, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\x10\x70\xD0", 32), 32))).$chunk('IEND', '');
        Storage::disk('public')->put('logo.png', $png);
        Storage::disk('public')->put('car.png', $png);
        $a['agency']->update(['logo' => 'logo.png', 'nom' => str_repeat('Agence Atlas ', 12), 'adresse' => str_repeat('Avenue de la location ', 15)]);
        $a['car']->update(['photo' => 'car.png']);
        $a['client']->update(['nom' => str_repeat('Client Long ', 15)]);
        $a['contract']->update(['notes' => str_repeat("Le véhicule doit être restitué dans un bon état.\n", 150).'FIN DES CONDITIONS']);
        $captured = null;
        View::composer('agence.contrats.pdf', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });
        $response = $this->actingAs($a['admin'])->get('/contrats/'.$a['contract']->id.'/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        if (function_exists('imagecreatefromstring')) {
            $this->assertStringStartsWith('data:image/png', $captured['agencyLogoDataUri']);
            $this->assertStringStartsWith('data:image/png', $captured['vehiclePhotoDataUri']);
        }
        $this->assertGreaterThan(1, preg_match_all('~/Type\s*/Page\b~', $response->getContent()));
        $this->assertStringContainsString('FIN DES CONDITIONS', view('agence.contrats.pdf', $captured)->render());
        if (getenv('GLV_PDF_REVIEW')) {
            file_put_contents(getenv('GLV_PDF_REVIEW'), $response->getContent());
        }
        Storage::disk('public')->put('logo.png', 'corrupt image');
        $this->get('/contrats/'.$a['contract']->id.'/pdf/preview')->assertOk();
    }

    public function test_real_pdf_download_and_inline_preview_only_render_owned_data_for_all_statuses(): void
    {
        Storage::fake('public');
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $captured = null;
        View::composer('agence.contrats.pdf', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });
        $this->actingAs($a['admin']);
        foreach (['actif', 'termine', 'annule'] as $status) {
            $a['contract']->update(['statut' => $status, 'notes' => '<script>alert(1)</script>']);
            foreach (['pdf' => 'attachment', 'pdf/preview' => 'inline'] as $suffix => $disposition) {
                $response = $this->get('/contrats/'.$a['contract']->id.'/'.$suffix);
                $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
                    ->assertHeader('X-Content-Type-Options', 'nosniff');
                $this->assertStringContainsString($disposition, $response->headers->get('Content-Disposition'));
                $this->assertStringContainsString('GLV-contrat-CTR-2026-A.pdf', $response->headers->get('Content-Disposition'));
                $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
                $this->assertStringStartsWith('%PDF-', $response->getContent());
                $this->assertGreaterThan(1000, strlen($response->getContent()));
                $html = view('agence.contrats.pdf', $captured)->render();
                foreach ([$a['agency']->nom, $a['client']->nom, $a['car']->immatriculation, $a['reservation']->reference, '600,00 MAD'] as $value) {
                    $this->assertStringContainsString($value, $html);
                }
                foreach ([$b['agency']->nom, $b['client']->nom, $b['car']->immatriculation, '<script>alert(1)</script>'] as $value) {
                    $this->assertStringNotContainsString($value, $html);
                }
            }
        }
        $this->get('/contrats/999999/pdf')->assertNotFound();
        $this->get('/contrats/999999/pdf/preview')->assertNotFound();
    }

    public function test_pdf_rejects_foreign_or_incoherent_relations_even_when_global_scope_hides_them(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        $this->actingAs($a['admin']);
        foreach (['client_id' => $b['client']->id, 'voiture_id' => $b['car']->id, 'reservation_id' => $b['reservation']->id] as $field => $id) {
            $original = $a['contract']->getAttribute($field);
            $a['contract']->update([$field => $id]);
            foreach (['pdf', 'pdf/preview'] as $suffix) {
                $this->get('/contrats/'.$a['contract']->id.'/'.$suffix)->assertForbidden();
            }
            $a['contract']->update([$field => $original]);
        }
    }

    public function test_missing_images_and_unsafe_reference_do_not_break_pdf_or_headers(): void
    {
        Storage::fake('public');
        $a = $this->tenant('A');
        $a['agency']->update(['logo' => '../outside.png']);
        $a['car']->update(['photo' => 'voitures/missing.jpg']);
        $a['contract']->update(['reference' => '../../contrat'."\r\n".'header', 'reservation_id' => null]);
        $response = $this->actingAs($a['admin'])->get('/contrats/'.$a['contract']->id.'/pdf')->assertOk();
        $this->assertStringNotContainsString('..', $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString("\r\n", $response->headers->get('Content-Disposition'));
    }
}
