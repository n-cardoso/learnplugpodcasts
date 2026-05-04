define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    const setupDragAndDrop = (config) => {
        const list = document.querySelector('[data-region="lp-episode-list"]');
        if (!list) {
            return;
        }

        let dragSource = null;

        list.querySelectorAll('.lp-episode-card').forEach((card) => {
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', (e) => {
                dragSource = card;
                e.dataTransfer.effectAllowed = 'move';
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!dragSource || dragSource === card) {
                    return;
                }
                const rect = card.getBoundingClientRect();
                const before = e.clientY < rect.top + rect.height / 2;
                if (before) {
                    list.insertBefore(dragSource, card);
                } else {
                    list.insertBefore(dragSource, card.nextSibling);
                }
                saveOrder(config);
            });
        });
    };

    const saveOrder = (config) => {
        const ids = Array.from(document.querySelectorAll('.lp-episode-card'))
            .map((el) => Number(el.dataset.episodeId || 0))
            .filter((id) => id > 0);

        Ajax.call([{
            methodname: 'mod_learnplugpodcasts_reorder_episodes',
            args: {
                cmid: config.cmid,
                episodeids: ids
            }
        }])[0].catch(Notification.exception);
    };

    const init = (config) => {
        if (!config || !config.cmid) {
            return;
        }
        setupDragAndDrop(config);
    };

    return {init};
});
