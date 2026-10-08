/*
 * asignua/filament-captcha: browser side.
 *
 * Defines window.filamentCaptcha(options), an Alpine data factory used as
 *     x-data="filamentCaptcha({ config, state: $wire.$entangle('data.captcha'), statePath: 'data.captcha' })"
 *
 * It is a plain script (no build step). Load it BEFORE Alpine starts: the widget Blade component and the
 * plugin's render hook both emit a <script src> tag (with the CSP nonce when there is one).
 *
 * Contract with the server:
 *  - the token ends up in `state` (a Livewire property, deferred: it travels with the next request);
 *  - after a request that carried the token, the widget is reset (tokens are single-use);
 *  - the window event `filament-captcha:reset` (dispatched by InteractsWithCaptcha::resetCaptcha()) resets too;
 *  - "execute" drivers (reCAPTCHA v3, invisible v2, invisible hCaptcha) fetch the token right before the
 *    enclosing form submits.
 */
(function () {
    'use strict';

    if (typeof window.filamentCaptcha === 'function') {
        return;
    }

    var loading = {};

    function loadScript(url, nonce) {
        if (loading[url]) {
            return loading[url];
        }

        loading[url] = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = url;
            script.async = true;
            script.defer = true;
            if (nonce) {
                script.setAttribute('nonce', nonce);
            }
            script.onload = function () { resolve(); };
            script.onerror = function () {
                delete loading[url];
                script.remove();
                reject(new Error('Captcha script failed to load: ' + url));
            };
            document.head.appendChild(script);
        });

        return loading[url];
    }

    function waitFor(test, timeout) {
        return new Promise(function (resolve, reject) {
            var started = Date.now();

            (function poll() {
                if (test()) {
                    resolve();
                } else if (Date.now() - started > timeout) {
                    reject(new Error('Captcha API did not become ready'));
                } else {
                    setTimeout(poll, 50);
                }
            })();
        });
    }

    function api(config) {
        if (config.kind === 'recaptcha') {
            return loadScript(config.scriptUrl, config.nonce)
                .then(function () { return waitFor(function () { return window.grecaptcha && window.grecaptcha.ready; }, 10000); })
                .then(function () { return new Promise(function (resolve) { window.grecaptcha.ready(resolve); }); });
        }

        var name = config.kind === 'turnstile' ? 'turnstile' : 'hcaptcha';

        return loadScript(config.scriptUrl, config.nonce)
            .then(function () { return waitFor(function () { return window[name] && window[name].render; }, 10000); });
    }

    function theme(config) {
        if (config.theme === 'light' || config.theme === 'dark') {
            return config.theme;
        }

        return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
    }

    window.filamentCaptcha = function (options) {
        var config = options.config || {};

        return {
            state: options.state === undefined ? '' : options.state,
            error: '',
            widgetId: null,
            destroyed: false,
            resolveToken: null,
            timer: null,
            offs: [],
            form: null,
            onSubmit: null,

            init: function () {
                var self = this;

                this.mount();

                var onReset = function () { self.reset(); };
                window.addEventListener('filament-captcha:reset', onReset);
                this.offs.push(function () { window.removeEventListener('filament-captcha:reset', onReset); });

                this.hookLivewire();
                this.bindForm();
            },

            destroy: function () {
                this.destroyed = true;
                clearInterval(this.timer);
                this.offs.forEach(function (off) { off(); });
                this.offs = [];

                if (this.form && this.onSubmit) {
                    this.form.removeEventListener('submit', this.onSubmit, true);
                }

                this.removeWidget();
            },

            mount: function () {
                var self = this;

                return api(config).then(function () {
                    if (self.destroyed) {
                        return null;
                    }

                    self.render();

                    if (config.driver === 'recaptcha_v3') {
                        self.timer = setInterval(function () { self.fetchToken(); }, Math.max(30, config.refresh || 100) * 1000);

                        return self.fetchToken();
                    }

                    return null;
                }).catch(function () {
                    self.error = (config.messages && config.messages.loadFailed) || 'The security check could not be loaded.';
                });
            },

            render: function () {
                var self = this;
                var el = this.$refs.widget;
                var done = function (token) { self.setToken(token); };
                var expired = function () { self.state = ''; };
                var failed = function () {
                    self.state = '';
                    self.error = (config.messages && config.messages.failed) || 'The security check failed. Try again.';
                };

                el.innerHTML = '';
                this.error = '';

                if (config.kind === 'recaptcha' && config.driver === 'recaptcha_v3') {
                    return;
                }

                if (config.kind === 'recaptcha') {
                    this.widgetId = window.grecaptcha.render(el, {
                        sitekey: config.siteKey,
                        theme: theme(config),
                        size: config.mode === 'execute' ? 'invisible' : (config.size || 'normal'),
                        callback: done,
                        'expired-callback': expired,
                        'error-callback': failed
                    });
                } else if (config.kind === 'turnstile') {
                    var params = {
                        sitekey: config.siteKey,
                        theme: theme(config),
                        callback: done,
                        'expired-callback': expired,
                        'error-callback': failed,
                        'timeout-callback': expired
                    };
                    if (config.size) { params.size = config.size; }
                    if (config.action) { params.action = config.action; }
                    if (config.locale) { params.language = config.locale; }
                    this.widgetId = window.turnstile.render(el, params);
                } else {
                    this.widgetId = window.hcaptcha.render(el, {
                        sitekey: config.siteKey,
                        theme: theme(config),
                        size: config.size || 'normal',
                        callback: done,
                        'expired-callback': expired,
                        'error-callback': failed
                    });
                }
            },

            setToken: function (token) {
                this.state = token;
                this.error = '';

                if (this.resolveToken) {
                    var resolve = this.resolveToken;
                    this.resolveToken = null;
                    resolve(token);
                }
            },

            fetchToken: function () {
                var self = this;

                return this.getToken().then(function (token) {
                    if (!self.destroyed) {
                        self.state = token;
                    }
                    return token;
                }).catch(function () {
                    self.error = (config.messages && config.messages.failed) || 'The security check failed. Try again.';
                    return '';
                });
            },

            /* A fresh token, right now (execute-style drivers only). */
            getToken: function () {
                var self = this;

                return api(config).then(function () {
                    if (config.driver === 'recaptcha_v3') {
                        return window.grecaptcha.execute(config.siteKey, { action: config.action || 'submit' });
                    }

                    return new Promise(function (resolve, reject) {
                        self.resolveToken = resolve;

                        if (config.kind === 'recaptcha') {
                            window.grecaptcha.execute(self.widgetId);
                        } else if (config.kind === 'hcaptcha') {
                            window.hcaptcha.execute(self.widgetId, { async: true }).then(function (result) {
                                self.setToken(result.response);
                            }).catch(reject);
                        } else {
                            resolve(self.state);
                        }
                    });
                });
            },

            reset: function () {
                this.state = '';
                this.resolveToken = null;

                try {
                    if (config.driver === 'recaptcha_v3') {
                        this.fetchToken();
                    } else if (this.widgetId !== null) {
                        if (config.kind === 'recaptcha') { window.grecaptcha.reset(this.widgetId); }
                        if (config.kind === 'turnstile') { window.turnstile.reset(this.widgetId); }
                        if (config.kind === 'hcaptcha') { window.hcaptcha.reset(this.widgetId); }
                    }
                } catch (e) {
                    this.render();
                }
            },

            removeWidget: function () {
                try {
                    if (this.widgetId !== null) {
                        if (config.kind === 'turnstile') { window.turnstile.remove(this.widgetId); }
                        if (config.kind === 'hcaptcha') { window.hcaptcha.remove(this.widgetId); }
                    }
                } catch (e) {
                    /* the widget is already gone */
                }

                this.widgetId = null;

                if (this.$refs && this.$refs.widget) {
                    this.$refs.widget.innerHTML = '';
                }
            },

            /* Tokens are single-use: once a request carried ours, the widget must start over. */
            hookLivewire: function () {
                var self = this;

                if (!window.Livewire || typeof window.Livewire.hook !== 'function' || !this.$wire) {
                    return;
                }

                var off = window.Livewire.hook('commit', function (context) {
                    var id = self.$wire.$id || (self.$wire.__instance && self.$wire.__instance.id);

                    if (id && context.component && context.component.id !== id) {
                        return;
                    }

                    if (!self.state || !self.carries(context.commit)) {
                        return;
                    }

                    context.succeed(function () { self.reset(); });
                });

                if (typeof off === 'function') {
                    this.offs.push(off);
                }
            },

            carries: function (commit) {
                var updates = commit && commit.updates;
                var path = options.statePath;

                if (!updates || typeof updates !== 'object' || !path) {
                    return true;
                }

                return Object.keys(updates).some(function (key) {
                    return key === path || key.indexOf(path + '.') === 0;
                });
            },

            /* Execute-style drivers: get the token right before the form goes out. */
            bindForm: function () {
                var self = this;

                if (config.mode !== 'execute') {
                    return;
                }

                this.form = this.$root.closest('form');

                if (!this.form) {
                    return;
                }

                var form = this.form;

                this.onSubmit = function (event) {
                    if (event.filamentCaptcha) {
                        return;
                    }

                    event.preventDefault();
                    event.stopImmediatePropagation();

                    self.getToken().then(function (token) {
                        self.state = token;

                        /* let Alpine's entangle push the value into the component before the handler reads it */
                        return new Promise(function (resolve) { setTimeout(resolve, 0); });
                    }).then(function () {
                        var again = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: event.submitter });
                        again.filamentCaptcha = true;

                        if (form.dispatchEvent(again)) {
                            form.submit();
                        }
                    }).catch(function () {
                        self.error = (config.messages && config.messages.failed) || 'The security check failed. Try again.';
                    });
                };

                form.addEventListener('submit', this.onSubmit, true);
            }
        };
    };
})();
