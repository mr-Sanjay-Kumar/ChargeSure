document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("registerForm");

    if (!form) {
        return;
    }

    form.addEventListener("submit", function (event) {

        const password = document.getElementById("password").value;
        const confirmPassword =
            document.getElementById("confirm_password").value;

        const message =
            document.getElementById("validationMessage");

        message.textContent = "";

        if (password.length < 8) {
            event.preventDefault();
            message.textContent =
                "Password must contain at least 8 characters.";
            return;
        }

        if (password !== confirmPassword) {
            event.preventDefault();
            message.textContent = "Passwords do not match.";
        }

    });

});