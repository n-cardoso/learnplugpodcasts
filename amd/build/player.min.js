define(['core/ajax', 'core/notification'], function(Ajax, Notification) {
    const SPEED_STEPS = [1, 1.25, 1.5, 2];

    const formatClock = (seconds) => {
        const total = Math.max(0, Math.floor(Number(seconds) || 0));
        const mins = Math.floor(total / 60);
        const secs = total % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const restorePosition = (player) => {
        const last = Number(player.dataset.lastPosition || 0);
        if (!last || Number.isNaN(last)) {
            return;
        }
        if (player.duration && last > 0 && last < player.duration - 1) {
            player.currentTime = last;
        }
    };

    const wireRestore = (player) => {
        player.addEventListener('loadedmetadata', () => {
            restorePosition(player);
        });
    };

    const setWaveState = (player, isplaying) => {
        const wave = player.closest('.lp-player-wrap')?.querySelector('[data-region="lp-wave"]');
        if (!wave) {
            return;
        }
        wave.classList.toggle('is-playing', !!isplaying);
    };

    const wireWave = (player) => {
        const update = () => {
            setWaveState(player, !player.paused && !player.ended);
        };
        ['play', 'pause', 'ended', 'waiting', 'stalled'].forEach((eventname) => {
            player.addEventListener(eventname, update);
        });
        update();
    };

    const updatePlayButton = (root, paused) => {
        const btn = root.querySelector('[data-action="play-toggle"]');
        if (!btn) {
            return;
        }
        btn.textContent = paused ? '▶' : '❚❚';
    };

    const parseTimestampToSeconds = (raw) => {
        const text = String(raw || '').trim().replace(',', '.');
        const match = text.match(/^((\d+):)?(\d{1,2}):(\d{2})(\.\d+)?$/);
        if (!match) {
            return NaN;
        }
        const hours = Number(match[2] || 0);
        const minutes = Number(match[3] || 0);
        const seconds = Number(match[4] || 0);
        const fraction = Number(match[5] || 0);
        return (hours * 3600) + (minutes * 60) + seconds + fraction;
    };

    const parseVtt = (content) => {
        const text = String(content || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const blocks = text.split(/\n{2,}/);
        const cues = [];

        blocks.forEach((block) => {
            const lines = block.split('\n').map((line) => line.trim()).filter((line) => line !== '');
            if (!lines.length) {
                return;
            }
            if (/^WEBVTT/i.test(lines[0]) || /^NOTE/i.test(lines[0])) {
                return;
            }

            let timingLineIndex = 0;
            if (!lines[0].includes('-->')) {
                timingLineIndex = 1;
            }
            const timingLine = lines[timingLineIndex] || '';
            if (!timingLine.includes('-->')) {
                return;
            }

            const parts = timingLine.split('-->');
            const startText = String(parts[0] || '').trim().split(/\s+/)[0];
            const endText = String(parts[1] || '').trim().split(/\s+/)[0];
            const start = parseTimestampToSeconds(startText);
            const end = parseTimestampToSeconds(endText);
            if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
                return;
            }

            const payload = lines.slice(timingLineIndex + 1).join('\n').trim();
            if (!payload) {
                return;
            }

            cues.push({
                start,
                end,
                text: payload.replace(/<[^>]+>/g, ''),
            });
        });

        return cues;
    };

    const initChannelPlayer = () => {
        const root = document.querySelector('[data-region="lp-channel-player"]');
        if (!root) {
            return;
        }

        const audio = root.querySelector('audio.lp-audio');
        if (!audio) {
            return;
        }

        const currentTime = root.querySelector('[data-region="lp-current-time"]');
        const totalTime = root.querySelector('[data-region="lp-total-time"]');
        const seekbar = root.querySelector('[data-region="lp-seekbar"]');
        const titleEl = root.querySelector('[data-region="lp-featured-title"]');
        const metaEl = root.querySelector('[data-region="lp-featured-meta"]');
        const descEl = root.querySelector('[data-region="lp-featured-description"]');
        const heroImage = root.querySelector('.lp-channel-hero-image');
        const progressLabel = root.querySelector('[data-region="lp-progress-label"]');
        const playlist = root.querySelector('[data-region="lp-playlist"]');
        const togglePlaylistBtn = root.querySelector('[data-action="toggle-playlist"]');
        const channelSearch = root.querySelector('[data-region="lp-channel-search"]');
        const channelSort = root.querySelector('[data-region="lp-channel-sort"]');
        const speedBtn = root.querySelector('[data-action="speed-toggle"]');
        const likeBtn = root.querySelector('[data-action="toggle-like"]');
        const likeIcon = root.querySelector('[data-region="lp-like-icon"]');
        const likeCount = root.querySelector('[data-region="lp-like-count"]');
        const captionSelectWrap = root.querySelector('[data-region="lp-caption-select-wrap"]');
        const captionSelect = root.querySelector('[data-region="lp-caption-select"]');
        const transcriptDetails = root.querySelector('[data-region="lp-transcript-details"]');
        const transcriptContent = root.querySelector('[data-region="lp-transcript-content"]');
        const transcriptLink = root.querySelector('[data-region="lp-transcript-link"]');
        const externalWrap = root.querySelector('[data-region="lp-featured-external-wrap"]');
        const externalLink = root.querySelector('[data-region="lp-featured-external-link"]');
        const captionOverlay = root.querySelector('[data-region="lp-caption-overlay"]');
        let captionCues = [];
        let captionLoadToken = 0;
        let likePending = false;

        const getPodcastRoot = () => root.closest('[data-region="lp-podcast"]');

        const setLikeUi = (liked, count) => {
            if (!likeBtn) {
                return;
            }
            const isliked = !!liked;
            const numericcount = Number(count || 0);
            const label = isliked ?
                String(likeBtn.dataset.unlikeLabel || 'Unlike episode') :
                String(likeBtn.dataset.likeLabel || 'Like episode');

            likeBtn.dataset.liked = isliked ? '1' : '0';
            likeBtn.classList.toggle('is-liked', isliked);
            likeBtn.setAttribute('aria-label', label);
            likeBtn.setAttribute('title', label);

            if (likeIcon) {
                likeIcon.textContent = isliked ? '♥' : '♡';
            }
            if (likeCount) {
                likeCount.textContent = String(Math.max(0, Math.floor(numericcount)));
            }
        };

        const parseCaptionTracks = (raw) => {
            const text = String(raw || '').trim();
            if (!text) {
                return [];
            }
            try {
                const parsed = JSON.parse(text);
                if (!Array.isArray(parsed)) {
                    return [];
                }
                return parsed
                    .map((item) => ({
                        lang: String(item.lang || '').trim(),
                        label: String(item.label || '').trim(),
                        url: String(item.url || '').trim(),
                    }))
                    .filter((item) => item.url !== '');
            } catch (e) {
                return [];
            }
        };

        const cleanCaptionLabel = (label, lang) => {
            const base = String(label || '')
                .replace(/[\u200E\u200F\u202A-\u202E\u2066-\u2069]/g, '')
                .trim();
            const code = String(lang || '').trim().toUpperCase();
            if (!base) {
                return code || 'Track';
            }
            if (!code) {
                return base;
            }
            // Remove trailing language-code suffixes before appending one canonical code.
            let withoutSuffix = base;
            const suffixPattern = /\s*[([]\s*[a-z]{2,3}(?:[-_][a-z0-9]{2,8})?\s*[)\]]\s*$/iu;
            while (suffixPattern.test(withoutSuffix)) {
                withoutSuffix = withoutSuffix.replace(suffixPattern, '').trim();
            }
            return `${withoutSuffix || base} (${code})`;
        };

        const normaliseTracks = (tracks, fallbackUrl, fallbackLang) => {
            if (Array.isArray(tracks) && tracks.length) {
                return tracks;
            }
            const url = String(fallbackUrl || '').trim();
            if (!url) {
                return [];
            }
            return [{
                lang: String(fallbackLang || '').trim(),
                label: String(fallbackLang || 'Captions').trim(),
                url,
            }];
        };

        const updateCaptionSelector = (tracks, selectedUrl) => {
            if (!captionSelectWrap || !captionSelect) {
                return;
            }
            const list = Array.isArray(tracks) ? tracks : [];
            captionSelect.innerHTML = '';

            const offOption = document.createElement('option');
            offOption.value = '';
            offOption.textContent = 'Off';
            captionSelect.appendChild(offOption);

            list.forEach((track) => {
                const option = document.createElement('option');
                option.value = track.url;
                option.dataset.lang = track.lang || '';
                option.textContent = cleanCaptionLabel(track.label || '', track.lang || '');
                if (selectedUrl && track.url === selectedUrl) {
                    option.selected = true;
                }
                captionSelect.appendChild(option);
            });

            if (!selectedUrl) {
                captionSelect.value = '';
            }
            captionSelectWrap.hidden = list.length === 0;
        };

        const updateCaption = () => {
            if (!captionOverlay || !captionCues.length) {
                if (captionOverlay) {
                    captionOverlay.textContent = '';
                    captionOverlay.hidden = true;
                }
                return;
            }

            const now = Number(audio.currentTime || 0);
            const cue = captionCues.find((item) => now >= item.start && now <= item.end);
            if (!cue) {
                captionOverlay.textContent = '';
                captionOverlay.hidden = true;
                return;
            }

            captionOverlay.textContent = cue.text;
            captionOverlay.hidden = false;
        };

        const loadCaptionTrack = (url) => {
            const trackUrl = String(url || '').trim();
            captionLoadToken += 1;
            const token = captionLoadToken;
            captionCues = [];
            updateCaption();

            if (!trackUrl) {
                return;
            }

            fetch(trackUrl, {credentials: 'same-origin'})
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Caption track fetch failed');
                    }
                    return response.text();
                })
                .then((content) => {
                    if (token !== captionLoadToken) {
                        return null;
                    }
                    captionCues = parseVtt(content);
                    updateCaption();
                    return null;
                })
                .catch(() => {
                    if (token !== captionLoadToken) {
                        return null;
                    }
                    captionCues = [];
                    updateCaption();
                    return null;
                });
        };

        const syncTimeUi = () => {
            const duration = Number(audio.duration || 0);
            const now = Number(audio.currentTime || 0);

            if (currentTime) {
                currentTime.textContent = formatClock(now);
            }

            if (totalTime && duration > 0) {
                totalTime.textContent = formatClock(duration);
            }

            if (seekbar) {
                if (duration > 0) {
                    seekbar.value = String(Math.max(0, Math.min(100, (now / duration) * 100)));
                } else {
                    seekbar.value = '0';
                }
            }
        };

        const getPlaylistItems = () => {
            if (!playlist) {
                return [];
            }
            return Array.from(playlist.querySelectorAll('.lp-playlist-item[data-action="select-episode"]'));
        };

        const applyDiscovery = () => {
            if (!playlist) {
                return;
            }

            const q = String(channelSearch?.value || '').trim().toLowerCase();
            const sortvalue = String(channelSort?.value || 'newest');
            const items = getPlaylistItems();

            items.sort((a, b) => {
                const at = Number(a.dataset.publishtime || 0);
                const bt = Number(b.dataset.publishtime || 0);
                return sortvalue === 'oldest' ? at - bt : bt - at;
            });
            items.forEach((item) => playlist.appendChild(item));

            items.forEach((item) => {
                const text = [
                    item.dataset.title || '',
                    item.dataset.meta || '',
                    item.dataset.description || '',
                ].join(' ').toLowerCase();
                const isactive = item.classList.contains('is-active');
                const matches = !q || text.includes(q) || isactive;
                item.hidden = !matches;
            });
        };

        const setActivePlaylistItem = (item) => {
            root.querySelectorAll('.lp-playlist-item').forEach((node) => node.classList.remove('is-active'));
            item.classList.add('is-active');
        };

        const syncFeaturedText = (item) => {
            if (titleEl) {
                titleEl.textContent = item.dataset.title || '';
            }
            if (metaEl) {
                metaEl.textContent = item.dataset.meta || '';
            }
            if (descEl) {
                descEl.textContent = item.dataset.description || '';
            }
            if (heroImage && item.dataset.image) {
                heroImage.src = item.dataset.image;
                heroImage.alt = item.dataset.title || heroImage.alt;
            }
            if (totalTime) {
                totalTime.textContent = item.dataset.durationLabel || totalTime.textContent;
            }
            if (progressLabel) {
                const percent = Math.round(Number(item.dataset.listenedPercent || 0));
                progressLabel.textContent = `Listened: ${percent}%`;
            }
            setLikeUi(
                Number(item.dataset.userLiked || 0) === 1,
                Number(item.dataset.likeCount || 0)
            );
        };

        const syncFeaturedTranscript = (item) => {
            if (transcriptContent) {
                transcriptContent.innerHTML = item.dataset.transcriptText || '';
            }
            if (transcriptDetails) {
                const hastext = !!(item.dataset.transcriptText || '').trim();
                transcriptDetails.style.display = hastext ? '' : 'none';
            }
            if (transcriptLink) {
                const fileurl = item.dataset.transcriptFileurl || '';
                transcriptLink.href = fileurl;
                transcriptLink.style.display = fileurl ? '' : 'none';
            }
        };

        const syncFeaturedExternal = (item) => {
            if (externalWrap && externalLink) {
                const externalurl = item.dataset.externalUrl || '';
                externalLink.href = externalurl;
                externalWrap.hidden = !externalurl;
            }
        };

        const syncAudioSource = (item, source, audioUrl) => {
            audio.dataset.episodeId = item.dataset.episodeId || '';
            audio.dataset.lastPosition = item.dataset.lastPosition || '0';
            audio.dataset.captionUrl = item.dataset.captionUrl || '';
            audio.dataset.captionLang = item.dataset.captionLang || '';
            audio.dataset.captionTracks = item.dataset.captionTracks || '[]';
            source.src = audioUrl;
            source.type = item.dataset.audiotype || source.type || 'audio/mpeg';
            setWaveState(audio, false);

            const parsedTracks = parseCaptionTracks(item.dataset.captionTracks || '[]');
            const tracks = normaliseTracks(parsedTracks, audio.dataset.captionUrl || '', audio.dataset.captionLang || '');
            updateCaptionSelector(tracks, audio.dataset.captionUrl || '');
            loadCaptionTrack(audio.dataset.captionUrl || '');
        };

        const persistLikeStateOnItem = (episodeid, liked, count) => {
            const targetid = Number(episodeid || 0);
            if (!targetid) {
                return;
            }

            const numericcount = String(Math.max(0, Math.floor(Number(count || 0))));
            const likedvalue = liked ? '1' : '0';
            getPlaylistItems().forEach((item) => {
                if (Number(item.dataset.episodeId || 0) === targetid) {
                    item.dataset.userLiked = likedvalue;
                    item.dataset.likeCount = numericcount;
                }
            });
        };

        const selectEpisode = (item) => {
            const audioUrl = item.dataset.audio || '';
            const source = audio.querySelector('source');
            if (!audioUrl || !source) {
                return;
            }

            setActivePlaylistItem(item);
            syncFeaturedText(item);
            syncFeaturedTranscript(item);
            syncFeaturedExternal(item);
            syncAudioSource(item, source, audioUrl);

            audio.load();
            audio.play().catch(() => null);
            updatePlayButton(root, false);
            applyDiscovery();
        };

        const bindCoreAudioEvents = () => {
            audio.addEventListener('play', () => {
                document.querySelectorAll('audio.lp-audio').forEach((other) => {
                    if (other !== audio && !other.paused) {
                        other.pause();
                    }
                });
                updatePlayButton(root, false);
            });
            audio.addEventListener('pause', () => updatePlayButton(root, true));
            audio.addEventListener('ended', () => updatePlayButton(root, true));
            audio.addEventListener('timeupdate', () => {
                syncTimeUi();
                updateCaption();
            });
            audio.addEventListener('loadedmetadata', () => {
                syncTimeUi();
                updateCaption();
            });
            audio.addEventListener('seeked', updateCaption);
            audio.addEventListener('ended', updateCaption);
        };

        const bindPlayerControls = () => {
            if (seekbar) {
                seekbar.addEventListener('input', () => {
                    const duration = Number(audio.duration || 0);
                    if (!duration) {
                        return;
                    }
                    const percent = Number(seekbar.value || 0);
                    audio.currentTime = Math.max(0, Math.min(duration, (percent / 100) * duration));
                    updateCaption();
                });
            }

            root.querySelector('[data-action="play-toggle"]')?.addEventListener('click', () => {
                if (audio.paused) {
                    audio.play().catch(() => null);
                } else {
                    audio.pause();
                }
            });

            root.querySelector('[data-action="seek-back"]')?.addEventListener('click', () => {
                audio.currentTime = Math.max(0, Number(audio.currentTime || 0) - 10);
            });

            root.querySelector('[data-action="seek-forward"]')?.addEventListener('click', () => {
                const duration = Number(audio.duration || 0);
                const target = Number(audio.currentTime || 0) + 10;
                audio.currentTime = duration > 0 ? Math.min(duration, target) : target;
            });

            if (speedBtn) {
                speedBtn.addEventListener('click', () => {
                    const current = Number(audio.playbackRate || 1);
                    const idx = SPEED_STEPS.findIndex((step) => step === current);
                    const next = SPEED_STEPS[(idx + 1) % SPEED_STEPS.length];
                    audio.playbackRate = next;
                    speedBtn.textContent = `×${next}`;
                });
            }

            if (captionSelect) {
                captionSelect.addEventListener('change', () => {
                    const selected = captionSelect.options[captionSelect.selectedIndex];
                    const url = selected ? String(selected.value || '').trim() : '';
                    const lang = selected ? String(selected.dataset.lang || '').trim() : '';
                    audio.dataset.captionUrl = url;
                    audio.dataset.captionLang = lang;
                    loadCaptionTrack(url);
                });
            }

            if (likeBtn) {
                likeBtn.addEventListener('click', () => {
                    if (likePending) {
                        return;
                    }
                    const podcastroot = getPodcastRoot();
                    const cmid = Number(podcastroot?.dataset.cmid || 0);
                    const episodeid = Number(audio.dataset.episodeId || 0);
                    if (!cmid || !episodeid) {
                        return;
                    }

                    likePending = true;
                    likeBtn.disabled = true;
                    Ajax.call([{
                        methodname: 'mod_learnplugpodcasts_toggle_like',
                        args: {
                            cmid: cmid,
                            episodeid: episodeid
                        }
                    }])[0].then((response) => {
                        const liked = Number(response.liked || 0) === 1;
                        const count = Number(response.likecount || 0);
                        setLikeUi(liked, count);
                        persistLikeStateOnItem(episodeid, liked, count);
                        return response;
                    }).catch(Notification.exception).finally(() => {
                        likePending = false;
                        likeBtn.disabled = false;
                    });
                });
            }
        };

        const bindPlaylistAndDiscovery = () => {
            if (togglePlaylistBtn && playlist) {
                togglePlaylistBtn.addEventListener('click', () => {
                    const expanded = togglePlaylistBtn.getAttribute('aria-expanded') === 'true';
                    togglePlaylistBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    playlist.hidden = expanded;
                });
            }

            root.querySelectorAll('.lp-playlist-item[data-action="select-episode"]').forEach((item) => {
                item.addEventListener('click', () => selectEpisode(item));
            });

            channelSearch?.addEventListener('input', applyDiscovery);
            channelSort?.addEventListener('change', applyDiscovery);
        };

        wireRestore(audio);
        wireWave(audio);
        syncTimeUi();
        updatePlayButton(root, audio.paused);
        if (transcriptDetails && transcriptContent) {
            const hastext = !!(transcriptContent.innerHTML || '').trim();
            transcriptDetails.style.display = hastext ? '' : 'none';
        }
        if (transcriptLink && !transcriptLink.getAttribute('href')) {
            transcriptLink.style.display = 'none';
        }
        if (externalWrap && externalLink && !externalLink.getAttribute('href')) {
            externalWrap.hidden = true;
        }
        const initialTracks = normaliseTracks(
            parseCaptionTracks(audio.dataset.captionTracks || '[]'),
            audio.dataset.captionUrl || '',
            audio.dataset.captionLang || ''
        );
        updateCaptionSelector(initialTracks, audio.dataset.captionUrl || '');
        loadCaptionTrack(audio.dataset.captionUrl || '');
        setLikeUi(
            Number(likeBtn?.dataset.liked || 0) === 1,
            Number(likeCount?.textContent || 0)
        );
        bindCoreAudioEvents();
        bindPlayerControls();
        bindPlaylistAndDiscovery();
        applyDiscovery();
    };

    const init = () => {
        if (!window.__lpSingleAudioBound) {
            window.__lpSingleAudioBound = true;
            document.addEventListener('play', (event) => {
                const target = event && event.target ? event.target : null;
                if (!(target instanceof HTMLMediaElement)) {
                    return;
                }
                document.querySelectorAll('audio, video').forEach((media) => {
                    if (media !== target && !media.paused) {
                        media.pause();
                    }
                });
            }, true);
        }

        document.querySelectorAll('audio.lp-audio').forEach((player) => {
            wireRestore(player);
            wireWave(player);
        });
        initChannelPlayer();
    };

    return {init};
});
