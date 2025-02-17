class PluginManagement {
    init() {
        $(document).on('click', '.btn-trigger-remove-plugin', (event) => {
            event.preventDefault()

            $('#confirm-remove-plugin-button').data('plugin', $(event.currentTarget).data('plugin'))
            $('#remove-plugin-modal').modal('show')
        })

        $(document).on('click', '#confirm-remove-plugin-button', (event) => {
            event.preventDefault()

            const _self = $(event.currentTarget)

            $httpClient
                .make()
                .withButtonLoading(_self)
                .delete(route('plugins.remove', { plugin: _self.data('plugin') }))
                .then(({ data }) => {
                    Botble.showSuccess(data.message)
                    window.location.reload()
                })
                .finally(() => $('#remove-plugin-modal').modal('hide'))
        })

        $(document).on('click', '.btn-trigger-update-plugin', (event) => {
            event.preventDefault()

            const _self = $(event.currentTarget)
            const uuid = _self.data('uuid')
            const name = _self.data('name')

            _self.prop('disabled', true)

            $httpClient
                .make()
                .withButtonLoading(_self)
                .post(route('plugins.marketplace.ajax.update', { id: uuid, name: name }))
                .then(({ data }) => {
                    Botble.showSuccess(data.message)

                    setTimeout(() => window.location.reload(), 2000)
                })
                .finally(() => _self.prop('disabled', false))
        })

        $(document).on('click', '.btn-trigger-change-status', async (event) => {
            event.preventDefault()

            const _self = $(event.currentTarget)

            const pluginName = _self.data('plugin')

            if (_self.data('status') === 1) {
                await this.activateOrDeactivatePlugin(pluginName)
                return
            }

            $httpClient
                .makeWithoutErrorHandler()
                .withButtonLoading(_self)
                .post(route('plugins.check-requirement', { name: pluginName }))
                .then(() => this.activateOrDeactivatePlugin(pluginName))
                .catch((e) => {
                    const { data, message } = e.response.data

                    if (data && data.existing_plugins_on_marketplace) {
                        const $modal = $('#confirm-install-plugin-modal')
                        $modal.find('.modal-body #requirement-message').html(message)
                        $modal.find('input[name="plugin_name"]').val(pluginName)
                        $modal.find('input[name="ids"]').val(data.existing_plugins_on_marketplace)
                        $modal.modal('show')

                        return
                    }

                    Botble.showError(message)
                })
        })

        $(document).on('click', '#confirm-install-plugin-button', async (event) => {
            const _self = $(event.currentTarget)

            Botble.showButtonLoading(_self)

            const $body = _self.parent().parent()

            const pluginName = $body.find('input[name="plugin_name"]').val()
            const pluginIds = $body.find('input[name="ids"]').val()
            const activatedPlugins = []

            for (const pluginId of pluginIds.split(',')) {
                const response = await this.installPlugin(pluginId)
                if (response) {
                    activatedPlugins.push(response.name)
                }
            }

            for (const pluginName of activatedPlugins) {
                await this.activateOrDeactivatePlugin(pluginName, false)
            }

            await this.activateOrDeactivatePlugin(pluginName)

            Botble.hideButtonLoading(_self)

            _self.text(_self.data('text'))
        })

        $(document).on('keyup', 'input[type="search"][name="search"]', (event) => {
            event.preventDefault()

            const search = $(event.currentTarget).val().toLowerCase()

            $('.plugin-item').each((index, element) => {
                const $element = $(element)
                const plugin = $element.data('plugin')

                const name = plugin.name.toLowerCase()
                const description = plugin.description.toLowerCase()

                if (name.includes(search) || description.includes(search)) {
                    $element.show()
                } else {
                    $element.hide()
                }
            })

            if ($('.plugin-item:visible').length === 0) {
                $('.empty').show()
            } else {
                $('.empty').hide()
            }
        })

        if ($('button[data-check-update]').length) {
            this.checkUpdate()
        }
    }

    checkUpdate() {
        $httpClient
            .make()
            .post(route('plugins.marketplace.ajax.check-update'))
            .then(({ data }) => {
                if (!data.data) {
                    return
                }

                Object.keys(data.data).forEach((key) => {
                    const plugin = data.data[key]

                    $(`button[data-check-update="${plugin.name}"]`)
                        .data('uuid', plugin.id)
                        .show()
                })
            })
    }

    async activateOrDeactivatePlugin(pluginName, reload = true) {
        return $httpClient
            .make()
            .put(route('plugins.change.status', { name: pluginName }))
            .then(({ data }) => {
                Botble.showSuccess(data.message)

                if (reload) {
                    window.location.reload()
                }
            })
    }

    async installPlugin(id) {
        return await $httpClient
            .make()
            .post(route('plugins.marketplace.ajax.install', { id }))
            .then(({ data }) => (data.error ? [] : data.data))
    }
}

$(() => {
    new PluginManagement().init()
})
