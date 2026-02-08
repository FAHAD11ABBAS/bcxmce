<?php
/*
Plugin Name: Display All User Profiles
Description: Tämä koodi hallinnoi "kaikki jäsenet" -sivua.
Version: 1.4
Author: Group Molto Bene
License: GPL2
*/

// === HARD OVERRIDE: render the "kaikki-profiilit" page via the theme template ===
// This guarantees profiles show for everyone on that URL, even if a builder/ACL hides content.
// Add ?no_force=1 to temporarily disable this behavior for debugging.
function mbp_profiles_page_id_force(): int {
    static $cached = null;
    if ($cached !== null) return $cached;

    // Find page by slug first
    $page = get_page_by_path('kaikki-profiilit', OBJECT, 'page');
    $id   = $page ? (int) $page->ID : 0;

    // Fallback to your known base ID if needed
    if (!$id) { $id = 31; }

    // Map to current language via WPML if available
    if (function_exists('apply_filters')) {
        $resolved = apply_filters('wpml_object_id', $id, 'page', true);
        if (is_numeric($resolved) && (int)$resolved > 0) $id = (int)$resolved;
    }
    return $cached = $id;
}

add_action('template_redirect', function () {
    if (is_admin() || isset($_GET['no_force'])) return;

    $target = mbp_profiles_page_id_force();
    if (!$target || !is_page($target)) return;

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

    get_header();

    echo '<main>';
      echo '<div id="content">';
        echo '<div id="site-content">';
          echo '<h2>Jäsenet</h2>';

          if (function_exists('display_all_user_profiles_shortcode')) {
              echo display_all_user_profiles_shortcode([]);
          } else {
              echo '<p>Profiles component unavailable.</p>';
          }

        echo '</div>'; // #site-content
      echo '</div>';   // #content
    echo '</main>';

    get_footer();
    exit;
}, 0);


// ------------------------------------------------------------
// Shortcode
// ------------------------------------------------------------
function display_all_user_profiles_shortcode($atts) {

    // mark that the shortcode actually executed
    $GLOBALS['mb_profiles_rendered_page31'] = true;

    ob_start();

    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

    $search_query = isset($_GET['search_user']) ? sanitize_text_field($_GET['search_user']) : '';

    // breadcrumb (hidden, survives minifiers)
    $cur_user = wp_get_current_user();
    $cur_lang = apply_filters('wpml_current_language', null);
    $def_lang = apply_filters('wpml_default_language', null);

    echo '<div style="display:none" data-debug="display_all_user_profiles"'
       . ' data-user="' . intval($cur_user->ID) . '"'
       . ' data-cur-lang="' . esc_attr($cur_lang ?: 'n/a') . '"'
       . ' data-def-lang="' . esc_attr($def_lang ?: 'n/a') . '"'
       . '></div>';

    // MAIN QUERY — bypass WPML restrictions
    $args = [
        'orderby'          => 'display_name',
        'order'            => 'ASC',
        'number'           => -1,
        'fields'           => 'all_with_meta',
        'count_total'      => false,
        'suppress_filters' => true,
    ];

    if ($search_query !== '') {
        $args['search']         = '*' . $search_query . '*';
        $args['search_columns'] = ['user_login','user_nicename','user_email','display_name'];
        $args['meta_query']     = [
            'relation' => 'OR',
            ['key' => 'first_name', 'value' => $search_query, 'compare' => 'LIKE'],
            ['key' => 'last_name',  'value' => $search_query, 'compare'  => 'LIKE'],
        ];
    }

    $users = get_users($args);

    // RAW SQL FALLBACK if still empty
    if (empty($users)) {
        global $wpdb;
        $where = '';
        $params = [];
        if ($search_query !== '') {
            $like = '%' . $wpdb->esc_like($search_query) . '%';
            $where = "WHERE u.user_login LIKE %s OR u.user_nicename LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s";
            $params = [$like,$like,$like,$like];
        }
        $sql = "SELECT u.ID FROM {$wpdb->users} u $where ORDER BY u.display_name ASC";
        $ids = $params ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);
        $users = array_map(fn($id) => get_user_by('id', (int)$id), $ids);
    }

    echo '<div style="display:none" data-debug="display_all_user_profiles_counts"'
       . ' data-count="' . (is_array($users) ? count($users) : 0) . '"'
       . '></div>';

    $current_user_id = get_current_user_id();

    // Display search form, department filter, and view toggle button
    ?>
    <form id="search-form" action="" method="get">
    <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>" />
    <input type="text" name="search_user" id="search-user-input" placeholder="Hae nimellä..." value="<?php echo esc_attr($search_query); ?>" />
    <!-- <div id="profile-search-buttons">
        <button id="reset">Kaikki jäsenet</button>
        <button id="toggle-honorary-members">Kunniajäsenet</button>
        <button id="toggle-year-executors">Vuoden executorit</button>
    </div> -->
    
    </form>
    

    <label for="department">Valitse alue:</label>
    <select id="department-filter">
        <option value="all">Kaikki alueet</option>
        <option value="Pirkanmaa">Pirkanmaa</option>
        <option value="Pohjanmaa">Pohjanmaa</option>
         <option value="Päijät-Häme&Kaakkois-Suomi">Päijät-Häme&Kaakkois-Suomi</option>
        <option value="Uusimaa">Uusimaa</option>
        <option value="Varsinais-Suomi">Varsinais-Suomi</option>
    </select>
    </br>
 <label for="nimitys">Valitse jäsentyyppi:</label>
<select id="role-filter">
    <option value="all">Kaikki jäsentyyppit</option>
    <option value="kokelas">Kokelas</option>
    <option value="jäsen">Jäsen</option>
    <option value="administrator">Ylläpitäjä</option>

</select>
    <button id="toggle-view" data-view="grid">Vaihda taulukkonäkymäksi</button>

    <?php
    if ($users) {
        echo '<div class="user-profiles grid-view">';

        // Separate the current user for priority display
        $current_user_profile = '';
        $other_users_profiles = '';

        foreach ($users as $user) {
            $roles = $user->roles;
            // Fetch the 'show_deactivated_member' meta value
            $show_deactivated_member = get_user_meta($user->ID, 'show_deactivated_member', true) === 'yes';
        
            // Check if the user has the 'deactivated' role and the 'show_deactivated_member' meta is not checked
            if (in_array('deactivated', $roles) && !$show_deactivated_member) {
                continue; // Skip this user if 'deactivated' role exists and 'show_deactivated_member' is not 'yes'
            } 
            $profile_picture = get_user_meta($user->ID, 'profile_picture', true) ?: get_avatar_url($user->ID, ['size' => 100]);
            $department = get_user_meta($user->ID, 'department', true);
            $nimitys = get_user_meta($user->ID, 'nimitys', true);
            $biographical_info = get_user_meta($user->ID, 'biographical_info', true);
            $user_email= ($user->user_email);
            //Get first aid and tilannekoulutus:
            $first_aid=get_user_meta($user->ID,'first_aid',true);
            if (!empty($first_aid)) {
                $first_aid = date('d.m.Y', strtotime($first_aid)); 
            }
            $tilanne_koulutus = get_user_meta($user->ID,'tilanne_koulutus',true);
            if (!empty($tilanne_koulutus)) {
                $tilanne_koulutus = date('d.m.Y', strtotime($tilanne_koulutus)); 
            }

            $phone_number=get_user_meta($user->ID,'phone_number',true);
            // Get visibility settings
            $hide_email = get_user_meta($user->ID, 'hide_email', true) === 'yes';
            $hide_phone_number = get_user_meta($user->ID, 'hide_phone_number', true) === 'yes';
            $custom_user_id = get_user_meta($user->ID, 'custom_user_id', true);

            $kunniajasen = !empty($nimitys) && strtolower(trim($nimitys)) === 'kunniajäsen';
            $honorary_number =get_user_meta($user->ID,'honorary_number',true);
            $appointed_date= get_user_meta($user->ID,'appointed_date',true);
            $vuoden_executor =get_user_meta($user->ID,'nimitys',true)==='Vuoden Executor';
            $vuoden_executor_date = get_user_meta($user->ID, 'vuoden_executor_date', true);
            

            ob_start();
            ?>
            <div class="user-profile" 
                 data-role="<?php echo esc_attr(isset($user->roles) ? implode(',', $user->roles) : ''); ?>"
                 data-department="<?php echo esc_attr(get_user_meta($user->ID, 'department', true)); ?>"
                 >
                <div class="user-avatar">
                    <img src="<?php echo esc_url($profile_picture); ?>" alt="<?php echo esc_attr($user->display_name); ?>'s Profile Picture">
                </div>
                <div class="user-details">
                    <h2><?php echo esc_html($user->display_name); ?></h2>
                    <h3 id="exe-num">#<?php echo esc_html(($custom_user_id));?></h3>
                    <p><strong>Nimi<br></strong> <?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></p>
                    <?php if(!empty($user->nimitys)): ?>
                    <p class="nimitys-text"><strong>Nimitys<br></strong> <?php echo esc_html($nimitys); ?> <?php if ($vuoden_executor): ?> <?php echo esc_attr($vuoden_executor_date); ?> <?php endif; ?> </p>
                    <?php endif; ?>
                    <?php if ($kunniajasen): ?>
                    <p><strong>Kunniajäsennumero<br></strong> <?php echo esc_attr($honorary_number);?></p>
                    <?php endif; ?>
                    <?php if (!empty($appointed_date) && $appointed_date !== '1970') : ?>
                    <p><strong> Nimitetty kunniajäseneksi<br></strong> <?php echo esc_attr($appointed_date); ?> 
                    <?php endif; ?>
                    <?php if (!$hide_email &&(!empty($user_email))) : ?>
                    <p><strong>Sähköposti<br></strong> <?php echo esc_html($user->user_email); ?></p>
                    <?php endif; ?>
                    <?php if (!$hide_phone_number &&(!empty($user->phone_number))) : ?>
                    <p><strong>Puhelinnumero<br></strong> <?php echo esc_html($phone_number); ?></p>
                    <?php endif; ?>
                    <?php if(!empty($user->department)): ?>
                    <p><strong>Alue<br></strong> <?php echo esc_html($department); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($user->motorcycle)) :?>
                    <p><strong>Moottoripyörä<br></strong> <?php echo esc_html($user->motorcycle); ?></p>
                    <?php endif; ?>
                    <?php if(!empty($user->company)): ?>
                    <p><strong>Yritys<br></strong> <?php echo esc_html($user->company); ?></p>
                    <?php endif ;?>
                    <?php if (!empty($first_aid) && $first_aid !== '01.01.1970') : ?>
                            <p><strong>Ensiapukoulutus suoritettu<br><?php echo esc_attr($first_aid); ?> </strong> 
                            <?php endif; ?>
                        <?php if (!empty($tilanne_koulutus) && $tilanne_koulutus !== '01.01.1970') : ?>
                            <p><strong>Tilannejohtamiskurssi suoritettu<br><?php echo esc_attr($tilanne_koulutus); ?> </strong> 
                            <?php endif; ?>
                            <?php if (!empty($biographical_info)): ?>
                                <div class="biography">
                        <textarea id="biographical_info" name="biographical_info" disabled><?php echo esc_textarea($biographical_info); ?></textarea>
                                </div>
                    <?php endif; ?>
                    <?php if ($current_user_id === (int) $user->ID) : ?>
                        <p><a href="<?php echo esc_url(get_permalink(get_page_by_path('oma-profiilisivu'))); ?>"class="edit-profile-button">Muokkaa profiiliasi</a></p>
                    <?php else: ?>
                        <p><a href="<?php echo esc_url(add_query_arg('user', $user->user_login, get_permalink(get_page_by_path('view-profile')))); ?>" class="view-profile-button">Näytä profiili</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $profile_output = ob_get_clean();

            if ($user->ID == $current_user_id) {
                $current_user_profile = $profile_output;
            } else {
                $other_users_profiles .= $profile_output;
            }
            
        }
        
        echo $current_user_profile;
        echo $other_users_profiles;

        echo '</div>';
        // Table structure for list view
        echo '<div id="user-table">';
        echo '<table class="user-profiles-table" style="display:none;">';
        
        echo '<thead>
        <tr>
            <th data-sortable="true" onclick="sortTable(0)">Nimi</th>
            <th data-sortable="true" onclick="sortTable(1)">Numero</th>
            <th data-sortable="true" onclick="sortTable(2)">Puhelinnumero</th>
            <th data-sortable="true" onclick="sortTable(3)">Sähköposti</th>
            <th data-sortable="true" onclick="sortTable(4)">Yritys</th>
        </tr>
    </thead>';
        echo '<tbody>';
        foreach ($users as $user) {
            $roles = $user->roles;
            // Fetch the 'show_deactivated_member' meta value
            $show_deactivated_member = get_user_meta($user->ID, 'show_deactivated_member', true) === 'yes';
        
            // Check if the user has the 'deactivated' role and the 'show_deactivated_member' meta is not checked
            if (in_array('deactivated', $roles) && !$show_deactivated_member) {
                continue; // Skip this user if 'deactivated' role exists and 'show_deactivated_member' is not 'yes'
            } 
            $hide_email = get_user_meta($user->ID, 'hide_email', true) === 'yes';
            $hide_phone_number = get_user_meta($user->ID, 'hide_phone_number', true) === 'yes';
            $biographical_info = get_user_meta($user->ID, 'biographical_info', true);
            $first_aid=get_user_meta($user->ID,'first_aid',true);
            if (!empty($first_aid)){
                $first_aid = date('d.m.Y',strtotime($first_aid));
            }
            $tilanne_koulutus= get_user_meta($user->ID,'tilanne_koulutus',true);
            if (!empty($tilanne_koulutus)) {
                $tilanne_koulutus = date('d.m.Y', strtotime($tilanne_koulutus)); 
            }
            $custom_user_id = get_user_meta($user->ID, 'custom_user_id', true);
            ?>
            <tr data-department="<?php echo esc_attr(get_user_meta($user->ID, 'department', true)); ?>" data-role="<?php echo esc_attr(isset($user->roles) ? implode(',', $user->roles) : ''); ?>">
                <td><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></td>
                <td><?php echo esc_html(($custom_user_id)); ?></td>
                <td><?php echo (!$hide_phone_number) ? esc_html(get_user_meta($user->ID, 'phone_number', true)) : 'Private'; ?></td>
                <td><?php echo (!$hide_email) ? esc_html($user->user_email) : 'Private'; ?></td>
                <td><?php echo esc_html($user->company); ?></td>
            </tr>
            
            <?php
        }
        echo '</tbody></table>';
        echo '</div>';
        ?>
    <?php
        //list for mobile
        echo '<div id="user-list" class="userListContainer" style="display:none;">'; 
       foreach ($users as $user) {
        $roles = $user->roles;
        // Fetch the 'show_deactivated_member' meta value
        $show_deactivated_member = get_user_meta($user->ID, 'show_deactivated_member', true) === 'yes';
    
        // Check if the user has the 'deactivated' role and the 'show_deactivated_member' meta is not checked
        if (in_array('deactivated', $roles) && !$show_deactivated_member) {
            continue; // Skip this user if 'deactivated' role exists and 'show_deactivated_member' is not 'yes'
        } 
        $hide_email = get_user_meta($user->ID, 'hide_email', true) === 'yes';
        $hide_phone_number = get_user_meta($user->ID, 'hide_phone_number', true) === 'yes';
        $custom_user_id = get_user_meta($user->ID, 'custom_user_id', true);
    ?>
<ul id="<?php echo esc_html($user->first_name . $user->last_name); ?>" 
    class="user-info-list" 
    data-department="<?php echo esc_attr(get_user_meta($user->ID, 'department', true)); ?>" 
    data-role="<?php echo esc_attr(!empty($user->roles) ? implode(',', $user->roles) : 'none'); ?>">

        <li class="list-name">
                <span class="label">Nimi:</span>
                <span class="value"><?php echo esc_html($user->first_name . ' ' . $user->last_name); ?></span>
            </li>
            <li>
                <span class="label">Numero:</span>
                <span class="value"><?php echo esc_html(($custom_user_id)); ?></span>
            </li>   
            <li>
                <span class="label">Puhelinnumero:</span>
                <span class="value"><?php echo (!$hide_phone_number) ? esc_html(get_user_meta($user->ID, 'phone_number', true)) : 'Private'; ?></span>
            </li>
            <li>
                <span class="label">Sähköposti:</span>
                <span class="value"><?php echo (!$hide_email) ? esc_html($user->user_email) : 'Private'; ?></span>
            </li>
       </ul>
        
        <?php
    }
       
       echo '</div>';


    } else {
        echo '<p>No user profiles found.</p>';
    }
   

    // JavaScript for view toggle and department filtering
    ?>
   <!-- JavaScript for view toggle, department filtering, and live search functionality -->
<script>
document.addEventListener('DOMContentLoaded', () => {

  // ---- OPTIONAL BUTTONS (only if they exist) ----
  let isShowingFiltered = false;

  const btnHonorary = document.getElementById('toggle-honorary-members');
  if (btnHonorary) {
    btnHonorary.addEventListener('click', function (event) {
      event.preventDefault();

      const profiles = document.querySelectorAll('.user-profile');
      if (!isShowingFiltered) {
        profiles.forEach(profile => {
          const nimitysText = profile.querySelector('.user-details .nimitys-text');
          profile.style.display = (nimitysText && nimitysText.textContent.includes('Kunniajäsen')) ? '' : 'none';
        });
      } else {
        profiles.forEach(profile => profile.style.display = '');
      }
      isShowingFiltered = !isShowingFiltered;
    });
  }

  const btnYear = document.getElementById('toggle-year-executors');
  if (btnYear) {
    btnYear.addEventListener('click', function (event) {
      event.preventDefault();

      const profiles = document.querySelectorAll('.user-profile');
      if (!isShowingFiltered) {
        profiles.forEach(profile => {
          const nimitysText = profile.querySelector('.user-details .nimitys-text');
          profile.style.display = (nimitysText && nimitysText.textContent.includes('Vuoden Executor')) ? '' : 'none';
        });
      } else {
        profiles.forEach(profile => profile.style.display = '');
      }
      isShowingFiltered = !isShowingFiltered;
    });
  }

  const btnReset = document.getElementById('reset');
  if (btnReset) {
    btnReset.addEventListener('click', function (event) {
      event.preventDefault(); // important if it's inside a form
      const input = document.getElementById("search-user-input");
      if (input) input.value = "";
    });
  }

  // ---- VIEW TOGGLE (exists in your code) ----
  const btnToggleView = document.getElementById('toggle-view');
  if (btnToggleView) {
    btnToggleView.addEventListener('click', function (event) {
      event.preventDefault();

      const userProfileContainer = document.querySelector('.user-profiles');
      const userTableContainer   = document.querySelector('.user-profiles-table');
      const userListContainer    = document.querySelector('#user-list');
      const currentView          = this.getAttribute('data-view');
      const wide                 = window.matchMedia("(min-width: 900px)");

      if (currentView === 'grid') {
        userProfileContainer.style.display = 'none';
        if (wide.matches) {
          userTableContainer.style.display = 'table';
          userListContainer.style.display = 'none';
        } else {
          userTableContainer.style.display = 'none';
          userListContainer.style.display = 'block';
        }
        this.setAttribute('data-view', 'table');
        this.textContent = 'Vaihda ruudukkonäkymäksi';
      } else {
        userProfileContainer.style.display = 'flex';
        userTableContainer.style.display = 'none';
        userListContainer.style.display = 'none';
        this.setAttribute('data-view', 'grid');
        this.textContent = 'Vaihda taulukkonäkymäksi';
      }
    });
  }

  // ---- FILTERS (this is what was breaking) ----
  const departmentFilter = document.getElementById('department-filter');
  const roleFilter       = document.getElementById('role-filter');

  if (departmentFilter) departmentFilter.addEventListener('change', filterProfiles);
  if (roleFilter)       roleFilter.addEventListener('change', filterProfiles);

  function filterProfiles() {
    const selectedDepartment = departmentFilter ? departmentFilter.value : 'all';
    const selectedRole       = roleFilter ? roleFilter.value : 'all';

    const profiles = document.querySelectorAll('.user-profile');
    const tables   = document.querySelectorAll('.user-profiles-table tbody tr');
    const lists    = document.querySelectorAll('.user-info-list');

    lists.forEach(list => {
      const department = list.getAttribute('data-department') || '';
      const roleAttr   = list.getAttribute('data-role');
      const roles      = roleAttr ? roleAttr.split(',') : [];

      const isVisible =
        (selectedDepartment === 'all' || department === selectedDepartment) &&
        (selectedRole === 'all' || roles.includes(selectedRole));

      list.style.display = isVisible ? '' : 'none';
    });

    profiles.forEach(profile => {
      const department = profile.getAttribute('data-department') || '';
      const roleAttr   = profile.getAttribute('data-role');
      const roles      = roleAttr ? roleAttr.split(',') : [];

      const isVisible =
        (selectedDepartment === 'all' || department === selectedDepartment) &&
        (selectedRole === 'all' || roles.includes(selectedRole));

      profile.style.display = isVisible ? '' : 'none';
    });

    tables.forEach(row => {
      const department = row.getAttribute('data-department') || '';
      const roleAttr   = row.getAttribute('data-role');
      const roles      = roleAttr ? roleAttr.split(',') : [];

      const isVisible =
        (selectedDepartment === 'all' || department === selectedDepartment) &&
        (selectedRole === 'all' || roles.includes(selectedRole));

      row.style.display = isVisible ? '' : 'none';
    });
  }

  // ---- LIVE SEARCH (unchanged, just safe-guarded) ----
  const searchInput = document.getElementById('search-user-input');
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const searchTerm = this.value.toLowerCase();
      const profiles = document.querySelectorAll('.user-profile');
      const rows = document.querySelectorAll('.user-profiles-table tbody tr');
      const list = document.querySelectorAll('.user-info-list');

      profiles.forEach(profile => {
        const strong = profile.querySelector('.user-details p strong');
        const nameText = strong && strong.nextSibling ? strong.nextSibling.textContent.trim().toLowerCase() : '';
        profile.style.display = nameText.includes(searchTerm) ? 'block' : 'none';
      });

      rows.forEach(row => {
        const name = (row.querySelector('td:nth-child(1)')?.textContent || '').toLowerCase() +
          ' ' + (row.querySelector('td:nth-child(2)')?.textContent || '').toLowerCase();
        row.style.display = name.includes(searchTerm) ? '' : 'none';
      });

      list.forEach(ul => {
        const userUl = (ul.id || '').toLowerCase();
        ul.style.display = userUl.includes(searchTerm) ? '' : 'none';
      });
    });
  }

  // keep your "current-user" highlight
  document.querySelectorAll('.user-profile').forEach(profile => {
    if (profile.querySelector('.edit-profile-button')) profile.classList.add('current-user');
  });

});
</script>

<style>
/* Add a pointer cursor to indicate interactivity */
.user-profiles-table th[data-sortable="true"] {
    cursor: pointer;
    background-color: #f8f9fa; /* Light background to differentiate */
    position: relative; /* For adding sort icons */
}

/* Highlight column headers on hover */
.user-profiles-table th[data-sortable="true"]:hover {
    background-color: #e2e6ea; /* Slightly darker shade */
    color: #007bff; /* Change text color for emphasis */
}

/* Sort icons */
.user-profiles-table th[data-sortable="true"]::after {
    content: '▲▼'; /* Placeholder sort arrows */
    font-size: 0.8em;
    position: absolute;
    color: #6c757d;
    right: 10px; /* Space between text and icon */
    opacity: 0.5;
}

/* Active sort (ascending) */
.user-profiles-table th[data-sortable="true"].sort-asc::after {
    content: '▲'; /* Show only ascending arrow */
    opacity: 1;
    color: #007bff; /* Highlight active sort */
}

/* Active sort (descending) */
.user-profiles-table th[data-sortable="true"].sort-desc::after {
    content: '▼'; /* Show only descending arrow */
    opacity: 1;
    color: #007bff; /* Highlight active sort */
}
</style>
    <?php

    return ob_get_clean();
}
add_shortcode('display_all_user_profiles', 'display_all_user_profiles_shortcode');

// Step 4: Add CSS for Styling (Optional)
function display_user_profiles_styles() {
    echo "
<style>
#search-form {
    width: 100%;
    text-align: center;
}

#profile-search-buttons {
    margin-bottom: 1rem;
}

#profile-search-buttons button {
    margin: .5rem;
}

 .user-profiles {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 20px;
    }

   .user-profiles a {
        text-decoration: none;
    }
    
    .user-profiles p {
        font-size: 15px;
        font-weight: normal;
    }
    
    .user-profile {
        border-radius: 8px;
        padding: 20px;
        background-color: #f9f9f9;
        max-width: 300px;
        flex: 1 1 calc(33% - 40px);
        box-shadow: 0.1rem 0.2rem 5px #5a6142;
        background-image: url(".plugins_url( '/images/4.jpg', __FILE__ ).");
    }

    .user-avatar {
        margin-bottom: 15px;
        text-align: center;
        position: relative;
        display: inline-block;
    }
    
    .user-avatar img {
        border-radius: 50%;
        width: 100px;
        height: 100px;
        object-fit: cover;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
    }
        
    .user-profile h2 {
        font-size: 1.25em;
        margin: 0 0 10px;
        font-family: 'EB Garamond', serif;
    }

    .user-profile p {
        margin: 5px 0;
        font-family: 'Montserrat', serif;
    }

    .view-profile-button a {
        display: inline-block;
        padding: 5px 20px;
        color: #1F2518;
        width: 12rem;
        font-weight: bold;
        background-color: #E2C275;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
    }
    
    .view-profile-button a:hover {
        background-color: #1F2518;
        color: #e2c275;
    }
        
    .edit-profile-button {
        display: inline-block;
        padding: 5px 20px;
        color: #1F2518;
        width: 12rem;
        font-weight: bold;
        background-color: #E2C275;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
        text-align: center;
    }

    .edit-profile-button:hover {
        background-color: #1F2518;
        color: #e2c275;
    }

/* Styles for table view */
        
    .user-profiles-table {
        border-collapse: collapse;
    }
    .user-profiles-table th, .user-profiles-table td {
        border: 1px solid #ddd;
        text-align: left;
        padding: 5px;
    }

    .user-profile .vip-crown{
        position: absolute;
        top: -15px;
        right: 0px;
        font-size: 24px;
        color: gold;
    }
            
    .biography textarea {
        resize: both;
        min-height: 80px;
        overflow: auto; 
        max-width:100%;
        padding: 5px;
        box-sizing:border-box;
    }
    .last-login {
        display: block;
        margin-top: 10px;
        font-size: 12px;
        color: #555;
        font-style: italic;
    }
            
 /* Highlight the user's own profile */

    .user-profile.current-user {
    position: relative;
    box-shadow: 0.1rem 0.2rem 5px #5a6142;
    border: solid 2px #5a6142;
    background-image: 
        linear-gradient(rgba(255, 255, 255, 1.00), rgba(255, 255, 255, 0.5)), 
        url(".plugins_url( '/images/6.jpg', __FILE__ ).");
    background-size: cover;
    background-position: center;
}
    
    .user-profile.user-profile.current-user p {
        color: #1F2518;
    }

    #search-user-input {
        width: 80%; 
        padding: 5px;
        margin-bottom: 40px;
        margin-right: 10px; 
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
        border: none;
        border-radius: 8px;
        font-family: 'Montserrat', serif;
    }

    button {
        padding: 5px 20px;
        background-color: #e2c275;
        color: #1F2518;
        border-radius: 8px;
        font-weight: bold;
        font-size: 0.75rem;
        border: none;
        cursor: pointer;
        font-family: 'Montserrat', serif;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
    }

    button:hover {
        background-color: #1F2518;
        color: #e2c275;
    }

    #toggle-view {
        margin-right: 10px; 
        float: right;
    }

    label[for='department'], label[for='nimitys']  {
        display: inline-block;
        width: 120px;
        font-size: 14px;
    }

    select#role-filter, select#department-filter {
        padding: 5px;
        box-shadow: 0.1rem 0.2rem 3px #5a6142;
        border-radius: 8px;
        border: none;
        margin-bottom: 10px;
        width: 200px; 
        font-family: 'Montserrat', serif;
    }

/* Highlight info field for VIP */

    .biography-logo {
        width: 100%; 
        height: 100%; 
        object-fit: contain;  
        display: block;
    } 
}

@media screen and (max-width:1150px) and (min-width: 920px){
        #content {
            width: 100%;   
        }
    }
    @media screen and (max-width: 900px) {
        #content {
            width: 100%;                 
        }

        #toggle-view {
            display: flex;
            float: none;
            margin-top: 1rem;
            margin-left: auto;
            margin-right: auto;
        }

        label[for='department'], label[for='nimitys']  {
            margin-left: 1.8rem;
        }
        
        #user-list ul {
            padding-left: 0;
        }

        

    }
     
/* Style for list view */
    .user-info-list {
        margin-top: 35px;
        font-size: 0.85rem;
        list-style-type: none;
        border-bottom: 1px solid black;
    }
    .user-info-list li {
        display: flex;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .user-info-list li:nth-child(odd){
        background-color: #ffffff;
    }
    .user-info-list li:nth-child(even){
        background-color: #f0f0e8;
    }
    .user-info-list li .label {
        flex-basis: 40%;
        margin-right: 10px;
    }
    .user-info-list li .value {
        flex-basis: 60%;
    }
            
}
         
</style>
    ";
}
add_action('wp_head', 'display_user_profiles_styles');
?>