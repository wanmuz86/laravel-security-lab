<?php
// Lab 6 Part A/C: sends a 302 to the local internal service, to show why redirects must not be followed.
header('Location: http://127.0.0.1:9001/internal.php', true, 302);
