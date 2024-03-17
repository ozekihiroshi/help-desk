<script>
    function confirmDelete(event) {
        let confirmation = confirm("Are you sure you want to delete this?");
        if (!confirmation) {
            event.preventDefault();
        }
    }
</script>