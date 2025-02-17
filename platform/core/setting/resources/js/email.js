$(document).ready(function () {
    $('[data-bb-toggle="test-email-send"]').on('click', (event) => {
        event.preventDefault()
        let _self = $(event.currentTarget)
        let form = new FormData(_self.closest('form')[0])

        Botble.showButtonLoading(_self)

        $httpClient
            .make()
            .postForm(route('settings.email.update'), form)
            .then(({ data }) => {
                Botble.showSuccess(data.message)
                $('#send-test-email-modal').modal('show')
            })
            .finally(() => {
                Botble.hideButtonLoading(_self)
            })
    })

    $('#send-test-email-btn').on('click', (event) => {
        event.preventDefault()
        let _self = $(event.currentTarget)

        Botble.showButtonLoading(_self)

        $httpClient
            .make()
            .post(route('settings.email.test.send'), {
                email: _self.closest('.modal-content').find('input[name=email]').val(),
            })
            .then(({ data }) => {
                Botble.showSuccess(data.message)
                _self.closest('.modal').modal('hide')
            })
            .finally(() => {
                Botble.hideButtonLoading(_self)
            })
    })
})
