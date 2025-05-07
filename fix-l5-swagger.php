<?php

// Leer el archivo original
$content = file_get_contents('config/l5-swagger.php');

// Realizar reemplazos para las líneas problemáticas
// Línea 187
$content = str_replace(
    "'type' => 'apiKey', // The type of the security scheme. Valid values are \"basic\", \"apiKey\" or \"oauth2\".",
    "'type' => 'apiKey', // The type of the security scheme (basic, apiKey, oauth2).",
    $content
);

// Línea 193
$content = str_replace(
    "'type' => 'oauth2', // The type of the security scheme. Valid values are \"basic\", \"apiKey\" or \"oauth2\".",
    "'type' => 'oauth2', // The type of the security scheme (basic, apiKey, oauth2).",
    $content
);

// Línea 195
$content = str_replace(
    "'flow' => 'implicit', // The flow used by the OAuth2 security scheme. Valid values are \"implicit\", \"password\", \"application\" or \"accessCode\".",
    "'flow' => 'implicit', // OAuth2 flow (implicit, password, application, accessCode).",
    $content
);

// Línea 196
$content = str_replace(
    "'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)",
    "'authorizationUrl' => 'http://example.com/auth', // Auth URL for implicit/accessCode",
    $content
);

// Línea 197
$content = str_replace(
    "//'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)",
    "//'tokenUrl' => 'http://example.com/auth' // Auth URL for password/application/accessCode",
    $content
);

// Línea 207
$content = str_replace(
    "'type' => 'apiKey', // Valid values are \"basic\", \"apiKey\" or \"oauth2\".",
    "'type' => 'apiKey', // Security type (basic, apiKey, oauth2).",
    $content
);

// Guardar el archivo modificado
file_put_contents('config/l5-swagger.php', $content);

echo "Archivo l5-swagger.php modificado correctamente.\n";
