(function () {
    function rootFrom(element) {
        return element ? element.closest('[data-ai-tryon-root]') : null;
    }

    function setMessage(root, text, state) {
        var message = root.querySelector('[data-ai-tryon-message]');

        if (!message) {
            return;
        }

        message.hidden = !text;
        message.dataset.state = state || '';
        message.textContent = text || '';
    }

    function setPremiumMessage(root, payload) {
        var message = root.querySelector('[data-ai-tryon-message]');

        if (!message) {
            return;
        }

        message.hidden = false;
        message.dataset.state = 'error';
        message.textContent = payload.message || 'You have reached the virtual try-on limit.';

        if (payload.premium_url) {
            var spacer = document.createTextNode(' ');
            var link = document.createElement('a');
            link.className = 'ai-tryon__premium';
            link.href = payload.premium_url;
            link.textContent = 'Upgrade';
            message.appendChild(spacer);
            message.appendChild(link);
        }
    }

    function setLoading(root, loading) {
        var submit = root.querySelector('[data-ai-tryon-submit]');

        if (submit) {
            submit.disabled = loading;
            submit.textContent = loading ? 'Creating preview...' : 'Create preview';
        }
    }

    function showResult(root, url) {
        var wrap = root.querySelector('[data-ai-tryon-result-wrap]');
        var image = root.querySelector('[data-ai-tryon-result]');

        if (wrap && image && url) {
            image.src = url;
            wrap.hidden = false;
        }
    }

    function openModal(root) {
        var modal = root.querySelector('[data-ai-tryon-modal]');

        if (modal) {
            modal.hidden = false;
        }
    }

    function closeModal(root) {
        var modal = root.querySelector('[data-ai-tryon-modal]');

        if (modal) {
            modal.hidden = true;
        }
    }

    function parseError(payload, fallback) {
        if (!payload) {
            return fallback;
        }

        if (payload.errors) {
            var firstKey = Object.keys(payload.errors)[0];

            if (firstKey && payload.errors[firstKey] && payload.errors[firstKey][0]) {
                return payload.errors[firstKey][0];
            }
        }

        return payload.message || fallback;
    }

    function pollStatus(root, url, attempts) {
        attempts = attempts || 0;

        window.setTimeout(function () {
            fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return {
                            ok: response.ok,
                            payload: payload
                        };
                    });
                })
                .then(function (result) {
                    var generation = result.payload.generation || {};

                    if (!result.ok) {
                        throw new Error(parseError(result.payload, 'Unable to check preview status.'));
                    }

                    if (generation.status === 'completed') {
                        showResult(root, generation.generated_image_url);
                        setMessage(root, 'Your try-on preview is ready.', 'success');
                        setLoading(root, false);
                        return;
                    }

                    if (generation.status === 'failed') {
                        setMessage(root, generation.error_message || 'The preview could not be generated.', 'error');
                        setLoading(root, false);
                        return;
                    }

                    if (attempts < 90) {
                        pollStatus(root, url, attempts + 1);
                    } else {
                        setMessage(root, 'The preview is still processing. Please check again shortly.', 'loading');
                        setLoading(root, false);
                    }
                })
                .catch(function (error) {
                    setMessage(root, error.message, 'error');
                    setLoading(root, false);
                });
        }, 2000);
    }

    document.addEventListener('click', function (event) {
        var open = event.target.closest('[data-ai-tryon-open]');
        var close = event.target.closest('[data-ai-tryon-close]');

        if (open) {
            openModal(rootFrom(open));
        }

        if (close) {
            closeModal(rootFrom(close));
        }
    });

    document.addEventListener('change', function (event) {
        var input = event.target.closest('[data-ai-tryon-file]');
        var root = rootFrom(input);

        if (!input || !root || !input.files || !input.files[0]) {
            return;
        }

        var previewWrap = root.querySelector('[data-ai-tryon-preview-wrap]');
        var preview = root.querySelector('[data-ai-tryon-preview]');

        if (previewWrap && preview) {
            preview.src = URL.createObjectURL(input.files[0]);
            previewWrap.hidden = false;
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-ai-tryon-form]');
        var root = rootFrom(form);

        if (!form || !root) {
            return;
        }

        event.preventDefault();
        setLoading(root, true);
        setMessage(root, 'Generating your try-on preview...', 'loading');

        fetch(root.dataset.aiTryonEndpoint, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return {
                        ok: response.ok,
                        status: response.status,
                        payload: payload
                    };
                });
            })
            .then(function (result) {
                var payload = result.payload;
                var generation = payload.generation || {};

                if (!result.ok) {
                    if (payload.code === 'limit_exceeded') {
                        setPremiumMessage(root, payload);
                        return;
                    }

                    throw new Error(parseError(payload, 'Unable to generate the try-on preview.'));
                }

                if (result.status === 202 && payload.status_url) {
                    setMessage(root, 'Your preview is processing...', 'loading');
                    pollStatus(root, payload.status_url, 0);
                    return;
                }

                if (generation.status === 'completed') {
                    showResult(root, generation.generated_image_url);
                    setMessage(root, 'Your try-on preview is ready.', 'success');
                    return;
                }

                if (generation.status === 'failed') {
                    setMessage(root, generation.error_message || 'The preview could not be generated.', 'error');
                }
            })
            .catch(function (error) {
                setMessage(root, error.message, 'error');
            })
            .finally(function () {
                var message = root.querySelector('[data-ai-tryon-message]');

                if (!message || message.dataset.state !== 'loading') {
                    setLoading(root, false);
                }
            });
    });
})();
