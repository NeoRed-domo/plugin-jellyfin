# Plugin Jellyfin para Jeedom

![Jellyfin Logo](../../plugin_info/jellyfin_icon.png)

Este plugin permite conectar su servidor **Jellyfin** a Jeedom para recuperar el estado de reproducción de sus diferentes reproductores (Clientes), controlarlos y navegar por su biblioteca multimedia.

## 🌟 Funcionalidades Principales

### 1. Información en tiempo real
* **Detección automática** de clientes Jellyfin activos en la red.
* **Estado de reproducción**: Reproducir, Pausa, Stop.
* **Información multimedia**: Título, Serie, Temporada, Episodio, Artista, Álbum.
* **Tiempo**: Duración total, posición actual y tiempo restante.
* **Visual**: Recuperación de la **carátula (Cover)** con gestión automática de la relación de aspecto (Cuadrado para música, Póster para películas).

### 2. Control del reproductor (Mando a distancia)
* Play / Pausa / Stop.
* Anterior / Siguiente.
* Control de posición (Seek) a través de una barra de progreso interactiva en el widget.
* *Nota: Optimizado para Android TV (Freebox POP, Shield...) con gestión de latencia.*

### 3. Explorador de Biblioteca
¡No necesita salir de Jeedom para elegir qué ver!
* Haga clic en el logotipo de Jellyfin del widget para abrir el explorador.
* **Navegación fluida** por sus carpetas, películas y música.
* **Miga de pan interactiva** (Breadcrumb) para volver fácilmente atrás.
* **Detalles del medio**: Visualización del resumen (sinopsis), año, calificación de la comunidad y duración.
* **Lanzamiento directo**: Inicie la reproducción en el equipo de destino con un simple clic.

### 4. Gestión de Favoritos
Cree accesos directos a su contenido favorito directamente en el widget.
* **Fácil de añadir**: Desde el explorador, haga clic en "Añadir a favoritos".
* **Acceso rápido**: Un cajón lateral en el widget muestra sus favoritos con sus carteles.
* **Lanzamiento en un clic**: Inicie su lista de reproducción o película favorita al instante.

### 5. Optimizaciones Técnicas
* **Demonio Python**: Conexión WebSocket reactiva y ligera.
* **Filtrado Inteligente**: Gestión limpia de equipos para evitar contaminar Jeedom.
* **Internacionalización**: Interfaz totalmente traducida (FR, EN, DE, ES).

---

## 🔧 Instalación y Configuración

1.  Instale el plugin desde el Market de Jeedom.
2.  Active el plugin.
3.  Instale las **dependencias** (necesario para el demonio Python).
4.  En la configuración del plugin:
    * Introduzca la **Dirección IP** de su servidor Jellyfin.
    * Introduzca el **Puerto** (por defecto `8096` o `443` si es HTTPS).
    * Introduzca la **Clave API** (Generar en Jellyfin: *Panel de control > Avanzado > Claves API*).
5.  Inicie el Demonio (Verifique que el estado sea OK).
6.  Inicie una reproducción en uno de sus dispositivos Jellyfin: el equipo se creará automáticamente en Jeedom.

---

## 📱 El Widget

El plugin incluye un widget dedicado, diseñado para integrarse perfectamente en el Dashboard:
* **Diseño oscuro** (Dark mode) siguiendo el estilo de Jellyfin.
* **Fondo dinámico** basado en la carátula del medio actual (efecto desenfocado).
* **Cajón de favoritos** retráctil para ahorrar espacio (haga clic en el corazón).
* **Botón de Biblioteca** (Logo Jellyfin) para explorar sus medios.

---

## ⚠️ FAQ y Notas
* **¿Por qué no aparece mi equipo?**: Inicie una reproducción en el dispositivo. El plugin solo crea los equipos cuando están activos por primera vez.
* **¿Control imposible?**: Algunos clientes (navegadores web, algunos televisores DLNA) no admiten el control remoto. El plugin mostrará la información, pero los botones Play/Pausa estarán inactivos.
* **¿Biblioteca vacía?**: Compruebe que su servidor Jellyfin esté encendido y sea accesible desde Jeedom.

---

**Autor:** NeoRed
**Licencia:** AGPL