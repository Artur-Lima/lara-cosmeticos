<?php

namespace Tests\Feature\Admin;

use App\Models\ProductImage;
use App\Support\ImageOptimizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_converte_para_webp_em_dois_tamanhos_sem_distorcer(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('foto-celular.jpg', 3000, 2000);

        $result = app(ImageOptimizer::class)->storeProductImage($file, 'products/1');

        $this->assertStringEndsWith('.webp', $result['path']);
        $this->assertStringEndsWith('-thumb.webp', $result['thumb_path']);

        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($result['path']));
        $this->assertSame([1200, 800, IMAGETYPE_WEBP], [$w, $h, $type]);

        [$tw, $th] = getimagesizefromstring(Storage::disk('public')->get($result['thumb_path']));
        $this->assertSame([480, 320], [$tw, $th]);
    }

    public function test_nao_amplia_imagem_pequena(): void
    {
        Storage::fake('public');

        $result = app(ImageOptimizer::class)->storeProductImage(UploadedFile::fake()->image('p.png', 300, 400), 'products/1');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($result['path']));
        $this->assertSame([300, 400], [$w, $h]);
    }

    public function test_arquivo_corrompido_gera_erro_claro(): void
    {
        Storage::fake('public');
        $this->expectException(RuntimeException::class);

        app(ImageOptimizer::class)->storeProductImage(UploadedFile::fake()->create('falsa.jpg', 5, 'image/jpeg'), 'products/1');
    }

    public function test_apagar_registro_apaga_os_dois_arquivos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/a.webp', 'x');
        Storage::disk('public')->put('products/1/a-thumb.webp', 'x');
        $image = ProductImage::factory()->create(['path' => 'products/1/a.webp', 'thumb_path' => 'products/1/a-thumb.webp']);

        $image->delete();

        Storage::disk('public')->assertMissing(['products/1/a.webp', 'products/1/a-thumb.webp']);
    }
}
