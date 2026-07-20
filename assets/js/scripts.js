;(function ($) {
    $(function () {
        var $master = $("input[name='clearance_price_cb']");
        if (!$master.length) {
            return;
        }

        // The dependent sections ("Product type", "Date Interval") are the
        // h2/table siblings rendered after the General Options table.
        var $sections = $master.closest("table").nextAll("h2, table");

        function toggleSections(animate) {
            if ($master.is(":checked")) {
                if (animate) {
                    $sections.fadeIn("slow");
                } else {
                    $sections.show();
                }
            } else if (animate) {
                $sections.fadeOut("slow");
            } else {
                $sections.hide();
            }
        }

        toggleSections(false);
        $master.on("change", function () {
            toggleSections(true);
        });
    });
})(jQuery);
