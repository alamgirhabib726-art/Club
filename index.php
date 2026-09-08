<?php
// Root fallback entry point for cloud deployments (Railway, Heroku, Docker)
chdir(__DIR__ . '/htdocs');
require_once __DIR__ . '/htdocs/index.php';
