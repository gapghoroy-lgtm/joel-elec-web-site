<?php
// Empêcher le listing du répertoire
http_response_code(403);
exit('Accès interdit.');
