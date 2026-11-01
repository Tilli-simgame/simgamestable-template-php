<?php
/**
 * Sovelluksen konfiguraatio
 * TÄRKEÄÄ: Tämä tiedosto on suojattu .htaccess:lla. Älä siirrä tätä pois src/includes/-kansiosta.
 */

// Sivuston asetukset
define('SITE_NAME', 'Virtuaalitalli Demotesti');
// Docker-kehitys: aseta SITE_URL ympäristömuuttujana (docker-compose.yml)
// Altervista: päivitä osoite tähän suoraan
define('SITE_URL', rtrim(getenv('SITE_URL') ?: 'https://tiikeriluola.fi/demotalli', '/'));

// Uploads-polku
define('UPLOADS_DIR', __DIR__ . '/../../uploads/');
define('UPLOADS_URL', SITE_URL . '/uploads/');

// Kuvan latauksen asetukset
define('MAX_PHOTOS_PER_HORSE', 5);
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Session-asetukset
define('SESSION_NAME', 'vt_session');
define('SESSION_LIFETIME', 3600); // 1 tunti
