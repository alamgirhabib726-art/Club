import { initializeApp } from 'firebase/app';
import { getFirestore, doc, getDocFromServer } from 'firebase/firestore';
import { getAuth } from 'firebase/auth';

const permanentFirebaseConfig = {
  projectId: "gen-lang-client-0538284921",
  appId: "1:254420466861:web:590bbb3862ded132369a24",
  apiKey: "AIzaSyDRgRiM6RqK6sSb6iUfvRoVCgyGBkXMja8",
  authDomain: "gen-lang-client-0538284921.firebaseapp.com",
  firestoreDatabaseId: "ai-studio-club-b1086cda-4804-4bf6-8fbe-75ce74987376",
  storageBucket: "gen-lang-client-0538284921.firebasestorage.app",
  messagingSenderId: "254420466861",
  measurementId: "",
  oAuthClientId: "254420466861-na9rujbf1ac6eijkqp3ttqr6vmlvp626.apps.googleusercontent.com"
};

// Initialize Firebase App instance
export const app = initializeApp(permanentFirebaseConfig);

// Initialize Firestore with specific databaseId provisioned by AI Studio
export const db = getFirestore(app, permanentFirebaseConfig.firestoreDatabaseId);

// Initialize Firebase Authentication
export const auth = getAuth(app);

// Connection test
export async function testConnection() {
  try {
    await getDocFromServer(doc(db, 'test', 'connection'));
  } catch (error) {
    if (error instanceof Error && error.message.includes('the client is offline')) {
      console.warn('Firebase client is offline or configuration requires network check:', error.message);
    }
  }
}

// Automatically test connection on import
testConnection().catch(() => {});
