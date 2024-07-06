<?php

namespace Tests\Integration\Services;

use App\Models\Playlist;
use App\Models\PlaylistFolder;
use App\Models\Song;
use App\Services\PlaylistService;
use App\Values\SmartPlaylistRuleGroupCollection;
use Illuminate\Support\Collection;
use InvalidArgumentException as BaseInvalidArgumentException;
use Tests\PlusTestCase;
use Tests\TestCase;
use Webmozart\Assert\InvalidArgumentException;

use function Tests\create_user;

class PlaylistServiceTest extends TestCase
{
    private PlaylistService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlaylistService::class);
    }

    public function testCreatePlaylist(): void
    {
        $user = create_user();

        $playlist = $this->service->createPlaylist('foo', $user);

        self::assertSame('foo', $playlist->name);
        self::assertTrue($user->is($playlist->user));
        self::assertFalse($playlist->is_smart);
    }

    public function testCreatePlaylistWithSongs(): void
    {
        /** @var array<array-key, Song>|Collection $songs */
        $songs = Song::factory(3)->create();

        $user = create_user();

        $playlist = $this->service->createPlaylist('foo', $user, null, $songs->pluck('id')->all());

        self::assertSame('foo', $playlist->name);
        self::assertTrue($user->is($playlist->user));
        self::assertFalse($playlist->is_smart);
        self::assertEqualsCanonicalizing($playlist->songs->pluck('id')->all(), $songs->pluck('id')->all());
    }

    public function testCreateSmartPlaylist(): void
    {
        $rules = SmartPlaylistRuleGroupCollection::create([
            [
                'id' => '45368b8f-fec8-4b72-b826-6b295af0da65',
                'rules' => [
                    [
                        'id' => '8cfa8700-fbc0-4078-b175-af31c20a3582',
                        'model' => 'title',
                        'operator' => 'is',
                        'value' => ['foo'],
                    ],
                ],
            ],
        ]);

        $user = create_user();

        $playlist = $this->service->createPlaylist('foo', $user, null, [], $rules);

        self::assertSame('foo', $playlist->name);
        self::assertTrue($user->is($playlist->user));
        self::assertTrue($playlist->is_smart);
    }

    public function testCreatePlaylistInFolder(): void
    {
        /** @var PlaylistFolder $folder */
        $folder = PlaylistFolder::factory()->create();

        $playlist = $this->service->createPlaylist('foo', $folder->user, $folder);

        self::assertSame('foo', $playlist->name);
        self::assertTrue($folder->ownedBy($playlist->user));
        self::assertTrue($playlist->inFolder($folder));
    }

    public function testCreatePlaylistInAnotherUsersFolder(): void
    {
        /** @var PlaylistFolder $folder */
        $folder = PlaylistFolder::factory()->create();

        self::expectException(InvalidArgumentException::class);

        $this->service->createPlaylist('foo', create_user(), $folder);
    }

    public function testUpdateSimplePlaylist(): void
    {
        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create(['name' => 'foo']);

        $this->service->updatePlaylist($playlist, 'bar');

        self::assertSame('bar', $playlist->name);
    }

    public function testUpdateSmartPlaylist(): void
    {
        $rules = SmartPlaylistRuleGroupCollection::create([
            [
                'id' => '45368b8f-fec8-4b72-b826-6b295af0da65',
                'rules' => [
                    [
                        'id' => '8cfa8700-fbc0-4078-b175-af31c20a3582',
                        'model' => 'title',
                        'operator' => 'is',
                        'value' => ['foo'],
                    ],
                ],
            ],
        ]);

        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create(['name' => 'foo', 'rules' => $rules]);

        $this->service->updatePlaylist($playlist, 'bar', null, SmartPlaylistRuleGroupCollection::create([
            [
                'id' => '45368b8f-fec8-4b72-b826-6b295af0da65',
                'rules' => [
                    [
                        'id' => '8cfa8700-fbc0-4078-b175-af31c20a3582',
                        'model' => 'title',
                        'operator' => 'is',
                        'value' => ['bar'],
                    ],
                ],
            ],
        ]));

        $playlist->refresh();

        self::assertSame('bar', $playlist->name);
        self::assertTrue($playlist->is_smart);
        self::assertSame($playlist->rule_groups->first()->rules->first()->value, ['bar']);
    }

    public function testSettingOwnsSongOnlyFailsForCommunityLicenseWhenCreate(): void
    {
        self::expectException(BaseInvalidArgumentException::class);
        self::expectExceptionMessage('"Own songs only" option only works with smart playlists and Plus license.');

        $this->service->createPlaylist(
            name: 'foo',
            user: create_user(),
            ruleGroups: SmartPlaylistRuleGroupCollection::create([
                [
                    'id' => '45368b8f-fec8-4b72-b826-6b295af0da65',
                    'rules' => [
                        [
                            'id' => '8cfa8700-fbc0-4078-b175-af31c20a3582',
                            'model' => 'title',
                            'operator' => 'is',
                            'value' => ['foo'],
                        ],
                    ],
                ],
            ]),
            ownSongsOnly: true
        );
    }

    public function testSettingOwnsSongOnlyFailsForCommunityLicenseWhenUpdate(): void
    {
        self::expectException(BaseInvalidArgumentException::class);
        self::expectExceptionMessage('"Own songs only" option only works with smart playlists and Plus license.');

        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->smart()->create();

        $this->service->updatePlaylist(
            playlist: $playlist,
            name: 'foo',
            ownSongsOnly: true
        );
    }

    public function testAddSongsToPlaylist(): void
    {
        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create();
        $playlist->addSongs(Song::factory(3)->create());
        $songs = Song::factory(2)->create();

        $addedSongs = $this->service->addSongsToPlaylist($playlist, $songs, $playlist->user);
        $playlist->refresh();

        self::assertCount(2, $addedSongs);
        self::assertCount(5, $playlist->songs);
        self::assertEqualsCanonicalizing($addedSongs->pluck('id')->all(), $songs->pluck('id')->all());
        $songs->each(static fn (Song $song) => self::assertTrue($playlist->songs->contains($song)));
    }

    public function testPrivateSongsAreMadePublicWhenAddedToCollaborativePlaylist(): void
    {
        PlusTestCase::enablePlusLicense();

        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create();
        $user = create_user();
        $playlist->collaborators()->attach($user);
        $playlist->refresh();
        self::assertTrue($playlist->is_collaborative);

        $songs = Song::factory(2)->create(['is_public' => false]);

        $this->service->addSongsToPlaylist($playlist, $songs, $user);

        $songs->each(static fn (Song $song) => self::assertTrue($song->refresh()->is_public));
    }

    public function testMakePlaylistSongsPublic(): void
    {
        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create();
        $playlist->addSongs(Song::factory(2)->create(['is_public' => false]));

        $this->service->makePlaylistSongsPublic($playlist);

        $playlist->songs->each(static fn (Song $song) => self::assertTrue($song->is_public));
    }

    public function testMoveSongsInPlaylist(): void
    {
        /** @var Playlist $playlist */
        $playlist = Playlist::factory()->create();

        /** @var Collection|array<array-key, Song> $songs */
        $songs = Song::factory(4)->create();
        $ids = $songs->pluck('id')->all();
        $playlist->addSongs($songs);

        $this->service->moveSongsInPlaylist($playlist, [$ids[2], $ids[3]], $ids[0], 'after');
        self::assertSame([$ids[0], $ids[2], $ids[3], $ids[1]], $playlist->refresh()->songs->pluck('id')->all());

        $this->service->moveSongsInPlaylist($playlist, [$ids[0]], $ids[3], 'before');
        self::assertSame([$ids[2], $ids[0], $ids[3], $ids[1]], $playlist->refresh()->songs->pluck('id')->all());

        // move to the first position
        $this->service->moveSongsInPlaylist($playlist, [$ids[0], $ids[1]], $ids[2], 'before');
        self::assertSame([$ids[0], $ids[1], $ids[2], $ids[3]], $playlist->refresh()->songs->pluck('id')->all());

        // move to the last position
        $this->service->moveSongsInPlaylist($playlist, [$ids[0], $ids[1]], $ids[3], 'after');
        self::assertSame([$ids[2], $ids[3], $ids[0], $ids[1]], $playlist->refresh()->songs->pluck('id')->all());
    }
}
