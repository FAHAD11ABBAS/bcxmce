<?php
/*
Template Name: Historia page
*/
?>

<?php get_header(); ?>

<div id="content">
    <main>
        <h2>Kaikki ajokaudet</h2>

        <div id="year-list" class="historia-filter">
            <ul>
            <?php for ($year = 2008; $year <= date('Y'); $year++): ?>
                <li>
                        <a href="#" class="year-link historia-filter__link" data-year="<?php echo esc_attr($year); ?>">
                            <?php echo esc_html($year); ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </div>

        <div id="h-places" class="historia-filter">
            <!-- Places will be loaded here via AJAX -->
        </div>

        <div id="h-events" class="historia-events">
            <!-- Events will be loaded here via AJAX -->
        </div>
    </main>
</div>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function() {
        const yearLinks = document.querySelectorAll(".year-link");

        // Function to set the active year link
        function setActiveYearLink(year) {
            // Remove active class from all links
            yearLinks.forEach(link => link.classList.remove("active"));
            
            // Find the link for the given year and add active class
            const yearLink = document.querySelector(`.year-link[data-year="${year}"]`);
            if (yearLink) {
                yearLink.classList.add("active");
            }
        }

        // Set the active year to the previous year (current year - 1), e.g., 2024 when it’s 2025
        const previousYear = new Date().getFullYear() - 1;
        setActiveYearLink(previousYear);  // Set the active year to 2024 if it's 2025

        yearLinks.forEach(link => {
            link.addEventListener("click", function(event) {
                event.preventDefault(); // Prevent the default link behavior

                // Remove active class from all links and add to the clicked one
                yearLinks.forEach(l => l.classList.remove("active"));
                this.classList.add("active");

                const year = this.getAttribute("data-year");

                // Send AJAX request to load events and places for the selected year
                fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            action: "filter_events",
                            year: year
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Update event list and place list
                        document.getElementById("h-events").innerHTML = data.events;
                        document.getElementById("h-places").innerHTML = data.places;
                        attachPlaceClickEvents(); // Re-attach click events for places
                    })
                    .catch(error => console.error("Error:", error));
            });
        });

        // Automatically load events for the previous year by default
        fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    action: "filter_events",
                    year: previousYear
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById("h-events").innerHTML = data.events;
                document.getElementById("h-places").innerHTML = data.places;
                attachPlaceClickEvents();
            })
            .catch(error => console.error("Error:", error));
    });

    function attachPlaceClickEvents() {
        const placeLinks = document.querySelectorAll(".place-link");
        placeLinks.forEach(link => {
            link.addEventListener("click", function(event) {
                event.preventDefault();

                // Remove the active class from all other place links
                placeLinks.forEach(l => l.classList.remove("active"));

                // Add the active class to the clicked place link
                this.classList.add("active");

                const placeId = this.getAttribute("data-place");
                const year = document.querySelector(".year-link.active")?.getAttribute("data-year");

                fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            action: "filter_events_by_place",
                            place_id: placeId,
                            year: year
                        })
                    })
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById("h-events").innerHTML = data;

                        // Scroll to the place heading after the events load
                        const placeHeading = document.getElementById(`place-${placeId}`);
                        if (placeHeading) {
                            placeHeading.scrollIntoView({
                                behavior: "smooth"
                            });
                        }
                    })
                    .catch(error => console.error("Error:", error));
            });
        });
    }
</script>

<?php get_footer(); ?>
