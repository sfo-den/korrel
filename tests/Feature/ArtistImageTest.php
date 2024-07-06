<?php

namespace Tests\Feature;

use App\Events\LibraryChanged;
use App\Models\Artist;
use App\Services\MediaMetadataService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

use function Tests\create_admin;

class ArtistImageTest extends TestCase
{
    private MediaMetadataService|MockInterface $mediaMetadataService;

    public function setUp(): void
    {
        parent::setUp();

        $this->mediaMetadataService = self::mock(MediaMetadataService::class);
    }

    public function testUpdate(): void
    {
        $this->expectsEvents(LibraryChanged::class);

        Artist::factory()->create(['id' => 9999]);

        $this->mediaMetadataService
            ->shouldReceive('writeArtistImage')
            ->once()
            ->with(Mockery::on(static fn (Artist $artist) => $artist->id === 9999), 'Foo', 'jpeg');

        $this->putAs('api/artist/9999/image', ['image' => 'data:image/jpeg;base64,Rm9v'], create_admin())
            ->assertOk();
    }

    public function testUpdateNotAllowedForNormalUsers(): void
    {
        Artist::factory()->create(['id' => 9999]);

        $this->mediaMetadataService->shouldNotReceive('writeArtistImage');

        $this->putAs('api/artist/9999/image', ['image' => 'data:image/jpeg;base64,Rm9v'])
            ->assertForbidden();
    }
}
