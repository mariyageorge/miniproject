import { initializeApp} from "https://www.gstatic.com/firebasejs/9.1.3/firebase-app.js";
import { getAuth,GoogleAuthProvider,onAuthStateChanged } from "https://www.gstatic.com/firebasejs/9.1.3/firebase-Auth.js";
const firebaseConfig = {
    apiKey: "AIzaSyBz1_t6bzHZ_P9f5-5srnGsH7dF1pdUOcM",
    authDomain: "signup-48a4e.firebaseapp.com",
    projectId: "signup-48a4e",
    storageBucket: "signup-48a4",
    messagingSenderId: "279728981782",
    appId: "1:279728981782:web:ee9859f7b5585b229ff237",
};


// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);


const user=auth.currentUser;

function updateUserProfile(user) {
    const userName = user.displayName;
    const userEmail = user.email;
    const userProfilePhoto = user.photoURL;
    console.log(user);
    document.getElementById("userName").textContent = userName;
    document.getElementById("userEmail").textContent = userEmail;
    document.getElementById("userProfilePhoto").src = userProfilePhoto;
}


onAuthStateChanged(auth, (user) => {
    if (user) {
        updateUserProfile(user);
        const uid=user.uid;
        return uid;
    } else {
        console.log("User is signed out");
        // window.location.href="/signup.php";
    }
});