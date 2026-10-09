// assets/js/script.js

document.addEventListener("DOMContentLoaded", function() {
    // Example: Form validation using Bootstrap
    var forms = document.querySelectorAll('.needs-validation')

    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
            }, false)
        })
});

function confirmDeletion(message) {
    return confirm(message || "Are you sure you want to perform this action?");
}

