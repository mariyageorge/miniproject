import { initializeApp } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-app.js";
import { getAuth, GoogleAuthProvider, signInWithPopup } from "https://www.gstatic.com/firebasejs/11.4.0/firebase-auth.js";

// Firebase Configuration
const firebaseConfig = {
    apiKey: "AIzaSyD1ypqe4qBoNz7l4qMLrRhSmbdqX_egoHQ",
    authDomain: "miniproject-25541.firebaseapp.com",
    projectId: "miniproject-25541",
    storageBucket: "miniproject-25541.firebasestorage.app",
    messagingSenderId: "521990011127",
    appId: "1:521990011127:web:db81c7bb4773a8d21a3bf2"
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
