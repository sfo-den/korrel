<?php

namespace App\Models;

use App\Builders\SongBuilder;
use App\Values\SongStorageMetadata\DropboxMetadata;
use App\Values\SongStorageMetadata\LegacyS3Metadata;
use App\Values\SongStorageMetadata\LocalMetadata;
use App\Values\SongStorageMetadata\S3CompatibleMetadata;
use App\Values\SongStorageMetadata\SongStorageMetadata;
use App\Values\SongStorageTypes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Throwable;

/**
 * @property string $path
 * @property string $title
 * @property Album $album
 * @property User $uploader
 * @property Artist $artist
 * @property Artist $album_artist
 * @property float $length
 * @property string $lyrics
 * @property int $track
 * @property int $disc
 * @property int $album_id
 * @property int|null $year
 * @property string $genre
 * @property string $id
 * @property int $artist_id
 * @property int $mtime
 * @property ?bool $liked Whether the song is liked by the current user (dynamically calculated)
 * @property ?int $play_count The number of times the song has been played by the current user (dynamically calculated)
 * @property Carbon $created_at
 * @property array<mixed> $s3_params
 * @property int $owner_id
 * @property bool $is_public
 * @property User $owner
 * @property-read SongStorageMetadata $storage_metadata
 * @property ?string $storage
 *
 * // The following are only available for collaborative playlists
 * @property-read ?string $collaborator_email The email of the user who added the song to the playlist
 * @property-read ?string $collaborator_name The name of the user who added the song to the playlist
 * @property-read ?int $collaborator_id The ID of the user who added the song to the playlist
 * @property-read ?string $added_at The date the song was added to the playlist
 */
class Song extends Model
{
    use HasFactory;
    use Searchable;
    use SupportsDeleteWhereValueNotIn;

    public const ID_REGEX = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public $incrementing = false;
    protected $guarded = [];

    protected $hidden = ['updated_at', 'path', 'mtime'];

    protected $casts = [
        'length' => 'float',
        'mtime' => 'int',
        'track' => 'int',
        'disc' => 'int',
        'is_public' => 'bool',
    ];

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(static fn (self $song) => $song->id = Str::uuid()->toString());

        static::saving(static function (self $song): void {
            if ($song->storage === '') {
                $song->storage = SongStorageTypes::LOCAL;
            }

            SongStorageTypes::assertValidType($song->storage);
        });
    }

    public static function query(): SongBuilder
    {
        return parent::query();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function newEloquentBuilder($query): SongBuilder
    {
        return new SongBuilder($query);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function album_artist(): BelongsTo // @phpcs:ignore
    {
        return $this->album->artist();
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    protected function title(): Attribute
    {
        return new Attribute(
            get: fn (?string $value) => $value ?: pathinfo($this->path, PATHINFO_FILENAME),
            set: static fn (string $value) => html_entity_decode($value)
        );
    }

    public function accessibleBy(User $user): bool
    {
        return $this->is_public || $this->ownedBy($user);
    }

    public function ownedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    protected function lyrics(): Attribute
    {
        $normalizer = static function (?string $value): string {
            // Since we're displaying the lyrics using <pre>, replace breaks with newlines and strip all tags.
            $value = strip_tags(preg_replace('#<br\s*/?>#i', PHP_EOL, $value));

            // also remove the timestamps that often come with LRC files
            return preg_replace('/\[\d{2}:\d{2}.\d{2}]\s*/m', '', $value);
        };

        return new Attribute(get: $normalizer, set: $normalizer);
    }

    protected function storageMetadata(): Attribute
    {
        return new Attribute(
            get: function (): SongStorageMetadata {
                try {
                    switch ($this->storage) {
                        case 's3':
                            preg_match('/^s3\\+:\\/\\/(.*)\\/(.*)/', $this->path, $matches);
                            return S3CompatibleMetadata::make($matches[1], $matches[2]);

                        case 's3-legacy':
                            preg_match('/^s3\\+:\\/\\/(.*)\\/(.*)/', $this->path, $matches);
                            return LegacyS3Metadata::make($matches[1], $matches[2]);

                        case 'dropbox':
                            preg_match('/^dropbox:\\/\\/(.*)/', $this->path, $matches);
                            return DropboxMetadata::make($matches[1]);

                        default:
                            return LocalMetadata::make($this->path);
                    }
                } catch (Throwable) {
                    return LocalMetadata::make($this->path);
                }
            }
        );
    }

    protected function s3Params(): Attribute
    {
        return Attribute::get(function (): ?array {
            if (!preg_match('/^s3:\\/\\/(.*)/', $this->path, $matches)) {
                return null;
            }

            [$bucket, $key] = explode('/', $matches[1], 2);

            return compact('bucket', 'key');
        });
    }

    public static function getPathFromS3BucketAndKey(string $bucket, string $key): string
    {
        return "s3://$bucket/$key";
    }

    /** @return array<mixed> */
    public function toSearchableArray(): array
    {
        $array = [
            'id' => $this->id,
            'title' => $this->title,
        ];

        if (!$this->artist->is_unknown && !$this->artist->is_various) {
            $array['artist'] = $this->artist->name;
        }

        return $array;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
