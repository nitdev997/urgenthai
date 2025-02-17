import PluginList from './components/PluginList.vue'

if (typeof vueApp !== 'undefined') {
    vueApp.booting((vue) => {
        vue.component('plugin-list', PluginList)
    })
}

$(() => {
    document.addEventListener('show-terms-and-policy-modal', (e) => {
        $('#terms-and-policy-modal').modal('show')
        $('#terms-and-policy-modal').find('[data-bb-toggle="accept-term-and-policy"]').data('id', e.detail.id)
    })

    $(document).on('click', '[data-bb-toggle="accept-term-and-policy"]', (e) => {
        const currentTarget = $(e.currentTarget)
        document.dispatchEvent(new CustomEvent('terms-and-policy-accepted', {
            detail: {
                element: currentTarget,
                id: currentTarget.data('id'),
            }
        }))
    })
})
