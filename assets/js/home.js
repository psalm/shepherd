(function () {
    'use strict';

    var clip = navigator.clipboard;
    var status = document.getElementById('copy-status');
    var input = document.getElementById('repo');
    var templates = document.querySelectorAll('[data-tpl]');

    if (clip) {
        document.querySelectorAll('[data-copy]').forEach(function (button) {
            var label = button.textContent;

            function say(text) {
                button.textContent = status.textContent = text;
                setTimeout(function () {
                    button.textContent = label;
                    status.textContent = '';
                }, 1500);
            }

            button.hidden = false;
            button.addEventListener('click', function () {
                clip.writeText(document.getElementById(button.dataset.copy).textContent).then(
                    function () { say('Copied'); },
                    function () { say('Copy failed'); }
                );
            });
        });
    }

    input.addEventListener('input', function () {
        var repo = /^[\w.-]+\/[\w.-]+$/.test(input.value) ? input.value : 'owner/repo';
        templates.forEach(function (el) {
            el.textContent = el.dataset.tpl.split('owner/repo').join(repo);
        });
    });
}());
