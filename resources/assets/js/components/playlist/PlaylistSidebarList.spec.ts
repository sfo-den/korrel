import { it } from 'vitest'
import { playlistFolderStore, playlistStore } from '@/stores'
import factory from '@/__tests__/factory'
import UnitTestCase from '@/__tests__/UnitTestCase'
import PlaylistSidebarList from './PlaylistSidebarList.vue'
import PlaylistSidebarItem from './PlaylistSidebarItem.vue'
import PlaylistFolderSidebarItem from './PlaylistFolderSidebarItem.vue'

new class extends UnitTestCase {
  private renderComponent () {
    return this.render(PlaylistSidebarList, {
      global: {
        stubs: {
          PlaylistSidebarItem,
          PlaylistFolderSidebarItem
        }
      }
    })
  }

  protected test () {
    it('displays orphan playlists', () => {
      playlistStore.state.playlists = [
        factory.states('orphan')<Playlist>('playlist', { name: 'Foo Playlist' }),
        factory.states('orphan')<Playlist>('playlist', { name: 'Bar Playlist' }),
        factory.states('smart', 'orphan')<Playlist>('playlist', { name: 'Smart Playlist' })
      ]

      const { getByText } = this.renderComponent()

      ;['Favorites', 'Recently Played', 'Foo Playlist', 'Bar Playlist', 'Smart Playlist'].forEach(t => getByText(t))
    })

    it('displays playlist folders', () => {
      playlistFolderStore.state.folders = [
        factory<PlaylistFolder>('playlist-folder', { name: 'Foo Folder' }),
        factory<PlaylistFolder>('playlist-folder', { name: 'Bar Folder' })
      ]

      const { getByText } = this.renderComponent()
      ;['Foo Folder', 'Bar Folder'].forEach(t => getByText(t))
    })
  }
}
