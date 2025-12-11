document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.completion-toggle').forEach(link => {
        link.addEventListener('click', function(e) {
            const current = this.dataset.current;
            const next = this.dataset.next;
            const username = this.dataset.username;
            const activity = this.dataset.activity;

            console.log('DEBUG:', username, activity, current, '→', next);

            const msg = `【${username}】の「${activity}」を\n${current} → ${next} に変更します。よろしいですか？`;
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});