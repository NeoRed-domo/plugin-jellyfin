# Registro de cambios

Este archivo enumera todos los cambios notables del plugin Jellyfin.

## [1.2.1] - 20-03-2026

🔧 **Correcciones**

* **Widget en tiempo real**: el título y el tipo de medio ahora se actualizan en tiempo real al cambiar de clip (listeners faltantes)
* **Carátula en tiempo real**: reemplazado el almacenamiento base64 (demasiado grande para los eventos de Jeedom) por una URL proxy ligera — la carátula se actualiza instantáneamente
* **Halo de monitoreo**: el halo verde en el clip activo ahora funciona en emisiones comerciales y sesiones de cine
* **Race condition double-fire**: las acciones del motor (lanzamiento, volumen, iluminación) ya no se disparan dos veces, gracias a la escritura anticipada del caché antes de las llamadas HTTP lentas

---

## [1.2.0] - 20-03-2026

🌟 **Normalización de audio y mejoras importantes**

### Normalización de audio (LUFS)
* **Calibración**: ruido rosa integrado (-24 LUFS), medición con ffmpeg, fórmula EBU R128
* **Perfiles de cine**: Noche (-20 dB), Cine (0 dB), THX (+10 dB), Manual (bypass)
* **Perfiles comerciales**: Silencio, Discreto (-20 dB), Normal (0 dB), Alto (+5 dB), Manual (bypass)
* **Control del amplificador**: volumen ajustado automáticamente clip por clip con offsets por sección
* **Tipo de salida de audio**: Amplificador (passthrough, corrección DRC AC3) o TV/PCM
* **Cambio de perfil en tiempo real** durante la reproducción

### Mejoras de sesiones
* **Monitoreo en vivo**: halo verde animado en la sección y el clip en reproducción
* **Progreso**: basado en la duración real de reproducción (no en el número de clips)
* **Badges técnicos**: resolución de vídeo y códec de audio mostrados en cada clip
* **Toggles**: activación/desactivación individual de secciones y disparadores
* **Contador de bucle** visible durante emisiones comerciales

### Documentación y traducciones
* **Documentación completa** en 4 idiomas (FR, EN, ES, DE)
* **305 cadenas traducidas** por idioma

### Correcciones notables
* Encadenamientos fiabilizados (playlist PlayNow + auto-avance de Jellyfin)
* Medición LUFS precisa (archivo temporal en lugar de pipe, corrección DRC AC3)
* Double-fire en auto-avance (escritura de caché inmediata)
* El comando de volumen del amplificador guardaba el nombre en lugar del ID

---

## [1.1.0] - 14-03-2026

🎬 **Sesiones de difusión**

### Sesiones de cine
* **7 secciones**: Preparación, Intro, Publicidad, Tráilers, Cortometraje, Trailer de audio, Película
* **Ambientes de iluminación**: escenario Jeedom por sección y por punto de referencia de película
* **Puntos de referencia calibrables**: pre-créditos, créditos 1, post-película 1, créditos 2, post-película 2, fin
* **Editor de sesiones**: interfaz de acordeón con colores por sección, arrastrar y soltar disparadores

### Emisiones comerciales
* **Playlist en bucle**: infinita, N veces o reproducción única
* **Encadenamiento automático** mediante playlist de Jellyfin

### Widget
* **Botón de sesión** (🎬) para lanzar una sesión desde el dashboard
* **Lista de sesiones** con póster, duración y estadísticas

### Motor de ejecución
* Daemon polling a 0.25s para máxima reactividad
* Máquina de estados (esperando lanzamiento, en reproducción, medio terminado)
* Detección de auto-avance de Jellyfin (resync)
* Precalentamiento del siguiente clip (pre-transcodificación)
* Proxy de vídeo HTTPS/HTTP para calibración

---

## [1.0.0] - 15-02-2026

🌍 **Primera versión estable**

* **Multi-idioma**: plugin traducido al inglés (en_US), alemán (de_DE) y español (es_ES)
* **Corrección**: botón de biblioteca del widget
* **Corrección**: sintaxis PHP en la página de configuración
* **Documentación**: enlaces y estructura para el Market

---

## [Beta] - 14-02-2026

🌟 **Mediateca y favoritos**

* **Explorador de biblioteca**: navegación, búsqueda, detalles de medios
* **Gestión de favoritos**: añadir, lanzamiento rápido, eliminación
* **Barra de progreso** mejorada
* **Filtrado inteligente** de clientes no controlables

---

## [Beta] - 12-02-2026

🎉 **Lanzamiento inicial**

* Detección automática de reproductores
* Control de medios: Play, Pause, Stop, Seek
* Metadatos e imágenes en tiempo real
* Widget dashboard con barra de progreso interactiva
* Daemon Python para conexión permanente
