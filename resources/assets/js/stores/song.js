import _ from 'lodash';

import http from '../services/http';
import utils from '../services/utils';
import stub from '../stubs/song';
import favoriteStore from './favorite';
import userStore from './user';

export default {
    stub,
    albums: [],
    cache: {},

    state: {
        songs: [stub],
    },

    /**
     * Init the store.
     * 
     * @param  {Array.<Object>} albums The array of albums to extract our songs from
     */
    init(albums) {
        // Iterate through the albums. With each, add its songs into our master song list.
        this.state.songs = _.reduce(albums, (songs, album) => {
            // While doing so, we populate some other information into the songs as well.
            _.each(album.songs, song => {
                song.fmtLength = utils.secondsToHis(song.length);

                // Keep a back reference to the album
                song.album = album;

                // Cache the song, so that byId() is faster
                this.cache[song.id] = song;
            });
            
            return songs.concat(album.songs);
        }, []);
    },

    /**
     * Initializes the interaction (like/play count) information.
     * 
     * @param  {Array.<Object>} interactions The array of interactions of the current user
     */
    initInteractions(interactions) {
        favoriteStore.clear();

        _.each(interactions, interaction => {
            var song = this.byId(interaction.song_id);
            
            if (!song) {
                return;
            }

            song.liked = interaction.liked;
            song.playCount = interaction.play_count;

            if (song.liked) {
                favoriteStore.add(song);
            }
        });
    },

    /**
     * Get the total duration of some songs.
     * 
     * @param {Array.<Object>}  songs
     * @param {Boolean}         toHis Whether to convert the duration into H:i:s format
     * 
     * @return {Float|String}
     */
    getLength(songs, toHis) {
        var duration = _.reduce(songs, (length, song) => length + song.length, 0);

        if (toHis) {
            return utils.secondsToHis(duration);
        }

        return duration;
    },

    /**
     * Get all songs.
     *
     * @return {Array.<Object>}
     */
    all() {
        return this.state.songs;
    },

    /**
     * Get a song by its ID.
     * 
     * @param  {String} id
     * 
     * @return {Object}
     */
    byId(id) {
        return this.cache[id];
    },

    /**
     * Get songs by their IDs.
     * 
     * @param  {Array.<String>} ids
     * 
     * @return {Array.<Object>}
     */
    byIds(ids) {
        return _.map(ids, id => this.byId(id));
    },

    /**
     * Increase a play count for a song.
     * 
     * @param  {Object} song
     */
    registerPlay(song) {
        // Increase playcount
        http.post('interaction/play', { id: song.id }, response => song.playCount = response.data.play_count);
    },

    /**
     * Get extra song information (lyrics, artist info, album info).
     * 
     * @param  {Object}     song 
     * @param  {?Function}  cb 
     */
    getInfo(song, cb = null) {
        if (!_.isUndefined(song.lyrics)) {
            if (cb) {
                cb();
            }
            
            return;
        }

        http.get(`${song.id}/info`, data => {
            song.lyrics = data.lyrics;

            // If the artist image is not in a nice form, don't use it.
            if (data.artist_info && typeof data.artist_info.image !== 'string') {
                data.artist_info.image = null;
            }
            
            song.album.artist.info = data.artist_info;
            
            // Set the artist image on the client side to the retrieved image from server.
            if (data.artist_info.image) {
                song.album.artist.image = data.artist_info.image;
            }

            // Convert the duration into i:s
            if (data.album_info && data.album_info.tracks) {
                _.each(data.album_info.tracks, track => track.fmtLength = utils.secondsToHis(track.length));
            }

            // If the album cover is not in a nice form, don't use it.
            if (data.album_info && typeof data.album_info.image !== 'string') {
                data.album_info.image = null;
            }

            song.album.info = data.album_info;

            // Set the album on the client side to the retrieved image from server.
            if (data.album_info.cover) {
                song.album.cover = data.album_info.cover;
            }

            if (cb) {
                cb();
            }
        });
    },

    /**
     * Scrobble a song (using Last.fm).
     * 
     * @param  {Object}     song
     * @param  {?Function}  cb 
     */
    scrobble(song, cb = null) {
        if (!window.useLastfm || !userStore.current().preferences.lastfm_session_key) {
            return;
        }

        http.post(`${song.id}/scrobble/${song.playStartTime}`, () => {
            if (cb) {
                cb();
            }
        });
    },
};
