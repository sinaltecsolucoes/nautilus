$(document).ready(function () {
    $('#auditTable').DataTable({
        language: {
            url: BASE_URL + '/assets/libs/Portuguese-Brasil.json'
        },
        pageLength: 25,
        order: [[6, 'desc']]
    });
});
