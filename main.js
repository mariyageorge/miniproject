import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

// Firebase Configuration
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
auth.languageCode = 'en';

const provider = new GoogleAuthProvider();
const googleLogin = document.getElementById("googleSignInBtn");

googleLogin.addEventListener("click", function () {
    signInWithPopup(auth, provider)
        .then((result) => {
            const user = result.user;
            console.log("✅ Google Sign-In Successful:", user);

            // Send user details to backend
            fetch("login.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `google_signin=true&email=${encodeURIComponent(user.email)}&name=${encodeURIComponent(user.displayName)}&google_id=${encodeURIComponent(user.uid)}`
            })
            .then(response => response.json())
            .then(data => {
                console.log("📨 Backend Response:", data);
                if (data.status === "success") {
                    sessionStorage.setItem("username", data.username);
                    window.location.href = data.redirect; // Redirect to the dashboard
                } else {
                    alert("🚨 Login failed: " + data.message);
                }
            })
            .catch(error => console.error("❌ Error during login:", error));
        })
        .catch((error) => {
            console.error("❌ Google Login Error:", error);
            alert("Google Sign-In failed: " + error.message);
        });
});
