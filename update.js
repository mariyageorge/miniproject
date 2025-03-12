import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

// Firebase Configuration (Make sure it matches main.js)
const firebaseConfig = {
    apiKey: "AIzaSyDsEXAOHEhdNEytfFHKP33U2xnoKfWui0g",
    authDomain: "signup-c33ba.firebaseapp.com",
    projectId: "signup-c33ba",
    storageBucket: "signup-c33ba.firebasestorage.app",
    messagingSenderId: "469751394942",
    appId: "1:469751394942:web:af2c338f00ffa4b1769e2f"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

// Listen for authentication state
onAuthStateChanged(auth, (user) => {
    if (user) {
        console.log("User is logged in:", user);
        document.getElementById("userName").textContent = user.displayName;
        document.getElementById("userEmail").textContent = user.email;
        document.getElementById("userProfilePhoto").src = user.photoURL;

        // Redirect to dashboard if user is logged in
        if (window.location.pathname.includes("login.php")) {
            window.location.href = "dashboard.php";
        }
    } else {
        console.log("No user logged in.");
        // Redirect to login page if not logged in
        if (!window.location.pathname.includes("login.php")) {
            window.location.href = "login.php";
        }
    }
});
