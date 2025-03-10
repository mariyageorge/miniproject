import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

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
            const userEmail = user.email;

            // Send email to backend for authentication
            fetch("google_login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "email=" + encodeURIComponent(userEmail)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "dashboard.php"; // Redirect after login
                } else {
                    alert("Login failed: " + data.message);
                }
            })
            .catch(error => console.error("Error:", error));
        })
        .catch((error) => {
            console.log("Google Login Error:", error);
        });
});
