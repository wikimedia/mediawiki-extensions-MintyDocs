$('#selectall').click(function() {
        $('input.mdCheckbox[type="checkbox"]').prop("checked", true);
});

$('#deselectall').click(function() {
        $('input.mdCheckbox[type="checkbox"]').prop("checked", false);
});