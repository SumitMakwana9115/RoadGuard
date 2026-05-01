$(document).ready(function() {
    
    // Sidebar Toggle
    $('.toggle-btn').on('click', function() {
        $('.sidebar').toggleClass('close');
    });

    // Theme Toggle implementation (with Cookies for state management)
    function setTheme(theme) {
        if (theme === 'dark') {
            $('html').addClass('dark');
            $('.mode-text').text('Light Mode');
            $('#toggleThemePublic i').removeClass('bx-moon').addClass('bx-sun');
        } else {
            $('html').removeClass('dark');
            $('.mode-text').text('Dark Mode');
            $('#toggleThemePublic i').removeClass('bx-sun').addClass('bx-moon');
        }
        // Save to cookie (Expires in 30 days)
        document.cookie = `theme_mode=${theme}; path=/; max-age=${30 * 24 * 60 * 60}`;
    }

    $('#toggleTheme, #toggleThemePublic').on('click', function(e) {
        e.preventDefault();
        let currentTheme = $('html').hasClass('dark') ? 'light' : 'dark';
        setTheme(currentTheme);
    });

    // Dynamic Location Dropdowns using AJAX API
    if ($('#ward_id').length) {
        $('#ward_id').on('change', function() {
            let wardId = $(this).val();
            let areaSelect = $('#area_id');
            let spotSelect = $('#spot_id');
            
            areaSelect.html('<option value="">Loading...</option>');
            spotSelect.html('<option value="">Select Area First</option>');

            if (!wardId) {
                areaSelect.html('<option value="">Select Ward First</option>');
                return;
            }

            $.ajax({
                url: BASE_URL + '/api/locations.php',
                type: 'GET',
                data: { action: 'get_areas', ward_id: wardId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let options = '<option value="">Select Area</option>';
                        response.data.forEach(function(area) {
                            options += `<option value="${area.id}">${area.name}</option>`;
                        });
                        areaSelect.html(options);
                    } else {
                        toastr.error('Failed to load areas');
                    }
                },
                error: function() {
                    toastr.error('Error connecting to server');
                }
            });
        });

        $('#area_id').on('change', function() {
            let areaId = $(this).val();
            let spotSelect = $('#spot_id');
            
            spotSelect.html('<option value="">Loading...</option>');

            if (!areaId) {
                spotSelect.html('<option value="">Select Area First</option>');
                return;
            }

            $.ajax({
                url: BASE_URL + '/api/locations.php',
                type: 'GET',
                data: { action: 'get_spots', area_id: areaId },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        let options = '<option value="">Select Spot</option>';
                        response.data.forEach(function(spot) {
                            options += `<option value="${spot.id}">${spot.name}</option>`;
                        });
                        spotSelect.html(options);
                    } else {
                        toastr.error('Failed to load spots');
                    }
                },
                error: function() {
                    toastr.error('Error connecting to server');
                }
            });
        });
    }

    // Duplicate Complaint Checker via AJAX
    if ($('#complaint_form').length) {
        let titleInput = $('#title');
        let categorySelect = $('#category_id');
        let dupWarning = $('<div class="text-danger mt-1 small" style="display:none; color: var(--warning-color);"><i class="bx bx-error-circle"></i> Potential duplicate complaint found in your history.</div>');
        titleInput.after(dupWarning);

        function checkDuplicate() {
            let title = titleInput.val();
            let cat = categorySelect.val();

            if (title.length > 5 && cat) {
                $.ajax({
                    url: BASE_URL + '/api/check_duplicate.php',
                    type: 'POST',
                    data: { title: title, category_id: cat },
                    dataType: 'json',
                    success: function(response) {
                        if (response.is_duplicate) {
                            dupWarning.slideDown();
                        } else {
                            dupWarning.slideUp();
                        }
                    }
                });
            }
        }

        titleInput.on('blur', checkDuplicate);
        categorySelect.on('change', checkDuplicate);
    }
});
