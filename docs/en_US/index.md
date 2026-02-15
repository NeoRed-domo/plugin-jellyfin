# Jellyfin Plugin for Jeedom

![Jellyfin Logo](../../plugin_info/jellyfin_icon.png)

This plugin allows you to connect your **Jellyfin** server to Jeedom to retrieve the playback status of your various players (Clients), control them, and navigate through your media library.

## 🌟 Main Features

### 1. Real-time Information Feedback
* **Automatic detection** of active Jellyfin clients on the network.
* **Playback status**: Play, Pause, Stop.
* **Media Information**: Title, Series, Season, Episode, Artist, Album.
* **Time**: Total duration, current position, and remaining time.
* **Visuals**: Retrieval of **Cover art** with automatic aspect ratio management (Square for music, Poster for movies).

### 2. Player Control (Remote)
* Play / Pause / Stop.
* Previous / Next.
* Position control (Seek) via an interactive progress bar on the widget.
* *Note: Optimized for Android TV (Freebox POP, Shield...) with latency management.*

### 3. Library Explorer (Media Center)
No need to leave Jeedom to choose what to watch!
* Click on the Jellyfin logo on the widget to open the explorer.
* **Fluid navigation** through your folders, movies, and music.
* **Interactive Breadcrumb** to easily go back up the hierarchy.
* **Media Details**: Display of summary (synopsis), year, community rating, and duration.
* **Direct Launch**: Start playback on the target device with a simple click.

### 4. Favorites Management
Create shortcuts to your favorite content directly on the widget.
* **Easy Add**: From the explorer, click "Add to favorites".
* **Quick Access**: A side drawer on the widget displays your favorites with their posters.
* **One-click Launch**: Launch your playlist or favorite movie instantly.

### 5. Technical Optimizations
* **Python Daemon**: Reactive and lightweight WebSocket connection.
* **Smart Filtering**: Clean management of devices to avoid polluting Jeedom.
* **Internationalization**: Fully translated interface (FR, EN, DE, ES).

---

## 🔧 Installation and Configuration

1.  Install the plugin from the Jeedom Market.
2.  Enable the plugin.
3.  Install **dependencies** (required for the Python daemon).
4.  In the plugin configuration:
    * Enter the **IP Address** of your Jellyfin server.
    * Enter the **Port** (default `8096` or `443` if HTTPS).
    * Enter the **API Key** (To be generated in Jellyfin: *Dashboard > Advanced > API Keys*).
5.  Start the Daemon (Check that status is OK).
6.  Start playback on one of your Jellyfin devices: the equipment will be automatically created in Jeedom.

---

## 📱 The Widget

The plugin includes a dedicated widget, designed to fit perfectly into the Dashboard:
* **Dark Mode design** following Jellyfin's aesthetic.
* **Dynamic background** based on the current media cover (blurred effect).
* **Retractable favorites drawer** to save space (click on the heart).
* **Library Button** (Jellyfin Logo) to browse your media.

---

## ⚠️ FAQ & Notes
* **Why doesn't my device appear?**: Start playback on the device. The plugin only creates equipment when they are active for the first time.
* **Control impossible?**: Some clients (web browsers, some DLNA TVs) do not support remote control. The plugin will report info, but Play/Pause buttons will be inactive.
* **Empty library?**: Check that your Jellyfin server is on and accessible from Jeedom.

---

**Author:** NeoRed
**License:** AGPL