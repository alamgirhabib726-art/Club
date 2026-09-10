<?php
/**
 * UNMOOR CLUB - FIREBASE ADAPTER & CONFIGURATION PROVIDER
 * Exposes provisioned Firebase Firestore and Auth credentials to PHP frontend and API endpoints.
 */

if (!defined('FIREBASE_INITIALIZED')) {
    define('FIREBASE_INITIALIZED', true);

    // Permanent hardcoded configuration for Railway and production deployments (zero manual config needed)
    $permanentConfig = [
        'projectId'           => 'gen-lang-client-0538284921',
        'appId'               => '1:254420466861:web:590bbb3862ded132369a24',
        'apiKey'              => 'AIzaSyDRgRiM6RqK6sSb6iUfvRoVCgyGBkXMja8',
        'authDomain'          => 'gen-lang-client-0538284921.firebaseapp.com',
        'firestoreDatabaseId' => 'ai-studio-club-b1086cda-4804-4bf6-8fbe-75ce74987376',
        'storageBucket'       => 'gen-lang-client-0538284921.firebasestorage.app',
        'messagingSenderId'   => '254420466861',
        'measurementId'       => '',
        'oAuthClientId'       => '254420466861-na9rujbf1ac6eijkqp3ttqr6vmlvp626.apps.googleusercontent.com',
        'recaptchaSiteKey'    => ''
    ];

    $firebaseConfig = $permanentConfig;

    // Check optional file paths for overrides
    $possiblePaths = [
        __DIR__ . '/../../firebase-applet-config.json',
        __DIR__ . '/../firebase-applet-config.json',
        __DIR__ . '/firebase-applet-config.json',
        (getenv('DOCUMENT_ROOT') ?: '') . '/../firebase-applet-config.json'
    ];

    foreach ($possiblePaths as $cfgPath) {
        if (!empty($cfgPath) && file_exists($cfgPath)) {
            $parsed = json_decode(file_get_contents($cfgPath), true);
            if (is_array($parsed) && !empty($parsed['apiKey'])) {
                $firebaseConfig = array_merge($firebaseConfig, $parsed);
                break;
            }
        }
    }

    // Check environment variables if explicitly set
    if (getenv('FIREBASE_API_KEY')) {
        $firebaseConfig['apiKey'] = getenv('FIREBASE_API_KEY');
    }
    if (getenv('FIREBASE_PROJECT_ID')) {
        $firebaseConfig['projectId'] = getenv('FIREBASE_PROJECT_ID');
    }
    if (getenv('FIREBASE_AUTH_DOMAIN')) {
        $firebaseConfig['authDomain'] = getenv('FIREBASE_AUTH_DOMAIN');
    }
    if (getenv('FIREBASE_DATABASE_ID')) {
        $firebaseConfig['firestoreDatabaseId'] = getenv('FIREBASE_DATABASE_ID');
    }

    define('FIREBASE_PROJECT_ID', $firebaseConfig['projectId']);
    define('FIREBASE_APP_ID', $firebaseConfig['appId']);
    define('FIREBASE_API_KEY', $firebaseConfig['apiKey']);
    define('FIREBASE_AUTH_DOMAIN', $firebaseConfig['authDomain']);
    define('FIREBASE_DATABASE_ID', $firebaseConfig['firestoreDatabaseId']);
    define('FIREBASE_STORAGE_BUCKET', $firebaseConfig['storageBucket']);
    define('FIREBASE_MESSAGING_SENDER_ID', $firebaseConfig['messagingSenderId']);

    /**
     * Helper to render client-side Firebase SDK loader
     */
    function render_firebase_sdk_scripts() {
        global $firebaseConfig;
        if (empty($firebaseConfig['apiKey'])) {
            return;
        }
        $configJson = json_encode($firebaseConfig);
        ?>
        <!-- Firebase Web SDK Integration -->
        <script type="module">
            import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
            import { getFirestore, doc, getDocFromServer } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-firestore.js";
            import { getAuth } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

            const firebaseConfig = <?= $configJson ?>;
            const app = initializeApp(firebaseConfig);
            const db = getFirestore(app, firebaseConfig.firestoreDatabaseId || "(default)");
            const auth = getAuth(app);

            window.unmoorFirebase = { app, db, auth, config: firebaseConfig };

            // Verify live connectivity
            (async () => {
                try {
                    await getDocFromServer(doc(db, "health", "ping"));
                } catch (e) {
                    // Offline fallback or permission status
                }
            })();
        </script>
        <?php
    }
}
