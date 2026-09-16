<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAgencyFixtures;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use CreatesAgencyFixtures, RefreshDatabase;

    public function test_legacy_documents_move_privately_without_data_loss_and_command_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $a = $this->tenant('A');
        $content = "%PDF-1.4\nPrivate document A";
        Storage::disk('public')->put($a['document']->fichier, $content);
        $this->artisan('glv:secure-documents --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists($a['document']->fichier);
        Storage::disk('documents')->assertMissing($a['document']->fichier);
        $this->artisan('glv:secure-documents')->assertSuccessful();
        $this->artisan('glv:secure-documents')->assertSuccessful();
        Storage::disk('public')->assertMissing($a['document']->fichier);
        $this->assertSame($content, Storage::disk('documents')->get($a['document']->fichier));
        $this->assertDatabaseHas('agence_documents', ['id' => $a['document']->id]);
        $this->actingAs($a['admin'])->get('/parametres/documents/'.$a['document']->id.'/telecharger')
            ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload('document-a.pdf');
    }

    public function test_document_paths_cannot_escape_the_agency_and_conflicting_private_copy_is_preserved(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        Storage::disk('documents')->put($b['document']->fichier, 'SECRET B');
        $this->actingAs($a['admin']);
        $original = $a['document']->fichier;
        foreach ([$b['document']->fichier, '../secret.pdf', 'agences/'.$a['agency']->id.'/documents/../../secret.pdf', 'C:\\secret.pdf'] as $path) {
            $a['document']->update(['fichier' => $path]);
            $this->get('/parametres/documents/'.$a['document']->id.'/telecharger')->assertForbidden();
            $this->delete('/parametres/documents/'.$a['document']->id)->assertForbidden();
        }
        Storage::disk('documents')->assertExists($b['document']->fichier);
        $a['document']->update(['fichier' => $original]);
        Storage::disk('public')->put($original, 'public content');
        Storage::disk('documents')->put($original, 'different private content');
        $this->artisan('glv:secure-documents')->assertFailed();
        $this->assertSame('public content', Storage::disk('public')->get($original));
        $this->assertSame('different private content', Storage::disk('documents')->get($original));
    }

    public function test_invalid_mime_extension_and_size_are_rejected_and_download_name_is_safe(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        $a = $this->tenant('A');
        $this->actingAs($a['admin']);
        $php = UploadedFile::fake()->createWithContent('danger.pdf', '<?php echo "bad";');
        foreach ([
            // Use the real MIME detector, not FakeFile's extension-derived MIME override.
            new UploadedFile($php->getPathname(), 'danger.pdf', 'application/pdf', null, true),
            UploadedFile::fake()->createWithContent('danger.php', "%PDF-1.4\nBad extension"),
            UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf'),
            UploadedFile::fake()->createWithContent('danger.svg', '<svg onload="alert(1)"></svg>'),
        ] as $file) {
            $this->postJson('/parametres/documents', ['nom' => 'Invalid', 'type' => 'autre', 'fichier' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('fichier');
        }
        $this->assertCount(0, Storage::disk('documents')->allFiles());
        $a['document']->update(['nom' => "../registre/\r\nInjected: header"]);
        Storage::disk('documents')->put($a['document']->fichier, "%PDF-1.4\nSafe");
        $response = $this->get('/parametres/documents/'.$a['document']->id.'/telecharger')->assertOk();
        $this->assertStringNotContainsString("\r\n", $response->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('../', $response->headers->get('Content-Disposition'));
    }
}
