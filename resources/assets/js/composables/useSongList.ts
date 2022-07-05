import { computed, provide, reactive, Ref, ref } from 'vue'
import isMobile from 'ismobilejs'
import { orderBy } from 'lodash'

import { playbackService } from '@/services'
import { queueStore, songStore } from '@/stores'
import router from '@/router'

import ControlsToggle from '@/components/ui/ScreenControlsToggle.vue'
import SongList from '@/components/song/SongList.vue'
import SongListControls from '@/components/song/SongListControls.vue'
import {
  SelectedSongsKey,
  SongListConfigKey,
  SongListSortFieldKey,
  SongListSortOrderKey,
  SongListTypeKey,
  SongsKey
} from '@/symbols'

export const useSongList = (
  songs: Ref<Song[]>,
  type: SongListType,
  config: Partial<SongListConfig> = {}
) => {
  const songList = ref<InstanceType<typeof SongList>>()

  const isPhone = isMobile.phone
  const selectedSongs = ref<Song[]>([])
  const showingControls = ref(false)

  const duration = computed(() => songStore.getFormattedLength(songs.value))

  const getSongsToPlay = (): Song[] => songList.value.getAllSongsWithSort()
  const playAll = (shuffle: boolean) => playbackService.queueAndPlay(getSongsToPlay(), shuffle)
  const playSelected = (shuffle: boolean) => playbackService.queueAndPlay(selectedSongs.value, shuffle)
  const toggleControls = () => (showingControls.value = !showingControls.value)

  const onPressEnter = async (event: KeyboardEvent) => {
    if (selectedSongs.value.length === 1) {
      queueStore.queueIfNotQueued(selectedSongs.value[0])
      await playbackService.play(selectedSongs.value[0])
      return
    }

    //  • Only Enter: Queue songs to bottom
    //  • Shift+Enter: Queues song to top
    //  • Cmd/Ctrl+Enter: Queues song to bottom and play the first selected song
    //  • Cmd/Ctrl+Shift+Enter: Queue songs to top and play the first queued song
    event.shiftKey ? queueStore.queueToTop(selectedSongs.value) : queueStore.queue(selectedSongs.value)

    if (event.ctrlKey || event.metaKey) {
      await playbackService.play(selectedSongs.value[0])
    }

    router.go('/queue')
  }

  const sortField = ref<SongListSortField | null>(((): SongListSortField | null => {
    if (type === 'album' || type === 'artist') return 'track'
    if (type === 'search-results') return null
    return config.sortable ? 'title' : null
  })())

  const sortOrder = ref<SortOrder>('asc')

  const sort = (by: SongListSortField | null = sortField.value, order: SortOrder = sortOrder.value) => {
    if (!by) return

    sortField.value = by
    sortOrder.value = order

    let sortFields: SongListSortField[] = [by]

    if (by === 'track') {
      sortFields.push('disc', 'title')
    } else if (by === 'album_name') {
      sortFields.push('artist_name', 'track', 'disc', 'title')
    } else if (by === 'artist_name') {
      sortFields.push('album_name', 'track', 'disc', 'title')
    }

    songs.value = orderBy(songs.value, sortFields, order)
  }

  provide(SongListTypeKey, type)
  provide(SongsKey, songs)
  provide(SelectedSongsKey, selectedSongs)
  provide(SongListConfigKey, reactive(config))
  provide(SongListSortFieldKey, sortField)
  provide(SongListSortOrderKey, sortOrder)

  return {
    SongList,
    SongListControls,
    ControlsToggle,
    songs,
    sortField,
    sortOrder,
    duration,
    songList,
    selectedSongs,
    showingControls,
    isPhone,
    onPressEnter,
    playAll,
    playSelected,
    toggleControls,
    sort
  }
}
