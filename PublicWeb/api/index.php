<?php

/* Avoids exposing any of our application code or configuration in the public web folder, preventing an
   Apache misconfiguration from leaking information if PHP code is served directly to the browser or if the
   ".phps" handler was turned on. */

require_once __DIR__ . '/../../API/app.php';