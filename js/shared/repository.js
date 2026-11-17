(function() {
    window.OCA = window.OCA || {};
    window.OCA.FlzRoom = window.OCA.FlzRoom || {};
    window.OCA.FlzRoom.Shared = window.OCA.FlzRoom.Shared || {};
    const shared = window.OCA.FlzRoom.Shared;

    class Repository {
        constructor(api) {
            this.api = typeof api === 'function' ? { request: api } : api;
        }

        request(path, options = {}) {
            return this.api.request(path, options);
        }

        post(path, body) {
            const options = { method: 'POST' };
            if (body !== undefined) {
                options.body = JSON.stringify(body);
            }
            return this.request(path, options);
        }

        encode(value) {
            return this.api.encode ? this.api.encode(value) : encodeURIComponent(String(value));
        }
    }

    shared.repositories = shared.repositories || {};
    shared.repositories.Repository = Repository;
})();
