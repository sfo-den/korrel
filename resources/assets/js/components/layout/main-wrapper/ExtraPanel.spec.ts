import { beforeEach, expect, it } from 'vitest'
import { cleanup, fireEvent } from '@testing-library/vue'
import { mockHelper, render } from '@/__tests__/__helpers__'
import ExtraPanel from './ExtraPanel.vue'
import LyricsPane from '@/__tests__/Stub.vue'
import AlbumInfo from '@/__tests__/Stub.vue'
import ArtistInfo from '@/__tests__/Stub.vue'
import YouTubeVideoList from '@/__tests__/Stub.vue'
import factory from '@/__tests__/factory'
import { commonStore } from '@/stores'
import { songInfoService } from '@/services'
import { eventBus } from '@/utils'

const renderComponent = () => {
  return render(ExtraPanel, {
    props: {
      song: factory<Song>('song')
    },
    global: {
      stubs: {
        LyricsPane,
        AlbumInfo,
        ArtistInfo,
        YouTubeVideoList
      }
    }
  })
}

beforeEach(() => {
  cleanup()
  mockHelper.restoreAllMocks()
})

it('has a YouTube tab if using YouTube ', () => {
  commonStore.state.useYouTube = true
  const { getByTestId } = renderComponent()

  getByTestId('extra-tab-youtube')
})

it('does not have a YouTube tab if not using YouTube', async () => {
  commonStore.state.useYouTube = false
  const { queryByTestId } = renderComponent()

  expect(await queryByTestId('extra-tab-youtube')).toBe(null)
})

it.each([['extra-tab-lyrics'], ['extra-tab-album'], ['extra-tab-artist']])('switches to "%s" tab', async (testId) => {
  const { getByTestId, container } = renderComponent()

  await fireEvent.click(getByTestId(testId))

  expect(container.querySelector('[aria-selected=true]')).toBe(getByTestId(testId))
})

it('fetches song info when a new song is played', () => {
  renderComponent()
  const song = factory<Song>('song')
  const mock = mockHelper.mock(songInfoService, 'fetch', song)

  eventBus.emit('SONG_STARTED', song)

  expect(mock).toHaveBeenCalledWith(song)
})
