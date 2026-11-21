/*
 * Copy buttons: <button class="copy-btn" data-copy="element-id" hidden>Copy</button> copies the text content of
 * that element. The buttons start hidden and are revealed only when the Clipboard API exists (secure contexts),
 * so nobody sees a button that cannot work.
 */
(function () {
    'use strict';

    var clip = navigator.clipboard;
    var buttons = document.querySelectorAll('[data-copy]');

    if (!clip || !buttons.length) {
        return;
    }

    var status = document.createElement('span');
    status.className = 'visually-hidden';
    status.setAttribute('role', 'status');
    document.body.appendChild(status);

    buttons.forEach(function (button) {
        var label = button.textContent;
        var timer = 0;

        function say(text) {
            button.textContent = status.textContent = text;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                button.textContent = label;
                status.textContent = '';
            }, 1500);
        }

        button.hidden = false;
        button.addEventListener('click', function () {
            var source = document.getElementById(button.dataset.copy);

            if (!source) {
                return;
            }

            clip.writeText(source.textContent).then(
                function () { say('Copied'); },
                function () { say('Copy failed'); }
            );
        });
    });
}());
