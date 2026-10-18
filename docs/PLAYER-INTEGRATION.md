# Public player milestone

NwOnlineTvPlayer renders escaped HTML5 video markup for valid HTTPS HLS metadata and a placeholder for rejected URLs.

This is a presenter, **not a fully functional cross-browser HLS implementation**. Native HLS playback is browser-dependent; hls.js or another appropriately licensed player may be used later with a reviewed Content Security Policy. Client playback does not prove stream availability or rights.

Still needed: native UNA view-page routing, permissions, player capability detection, safe iframe restrictions if added, and cross-browser end-to-end tests. No playback URL is fetched by the server-side presenter.
