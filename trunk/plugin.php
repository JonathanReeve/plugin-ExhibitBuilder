<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2008
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @subpackage ExhibitBuilder
 **/
define('EXHIBIT_BUILDER_VERSION', get_plugin_ini('ExhibitBuilder', 'version'));
define('EXHIBIT_PLUGIN_DIR', dirname(__FILE__));

define('WEB_EXHIBIT_PLUGIN_DIR', WEB_PLUGIN . '/' . basename(dirname(__FILE__)));
define('EXHIBIT_THEMES_DIR_NAME', 'exhibit_themes');
define('EXHIBIT_LAYOUTS_DIR_NAME', 'exhibit_layouts');
define('EXHIBIT_THEMES_DIR', EXHIBIT_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'views'
. DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . EXHIBIT_THEMES_DIR_NAME);
define('EXHIBIT_LAYOUTS_DIR', EXHIBIT_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'views'
. DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'exhibit_layouts');

require_once 'Exhibit.php';

add_plugin_hook('install', 'exhibit_builder_install');
add_plugin_hook('define_acl', 'exhibit_builder_setup_acl');
add_plugin_hook('define_routes', 'exhibit_builder_routes');
add_plugin_hook('public_theme_header', 'exhibit_builder_public_header');
add_plugin_hook('admin_theme_header', 'exhibit_builder_admin_header');
add_plugin_hook('admin_append_to_dashboard_primary', 'exhibit_builder_dashboard');

add_filter('public_navigation_main', 'exhibit_builder_public_main_nav');
add_filter('admin_navigation_main', 'exhibit_builder_admin_nav');

// This hook is defined in the HtmlPurifier plugin, meaning this will only work
// if that plugin is enabled.
add_plugin_hook('html_purifier_form_submission', 'exhibit_builder_purify_html');


// Helper functions for exhibits
require_once EXHIBIT_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'ExhibitFunctions.php';

function exhibit_builder_install() {
	set_option('exhibit_builder_version', EXHIBIT_BUILDER_VERSION);
	
	$db = get_db();
	$db->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix}exhibits` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `title` varchar(255) collate utf8_unicode_ci default NULL,
      `description` text collate utf8_unicode_ci,
      `credits` text collate utf8_unicode_ci,
      `featured` tinyint(1) default '0',
      `public` tinyint(1) default '0',
      `theme` varchar(30) collate utf8_unicode_ci default NULL,
      `slug` varchar(30) collate utf8_unicode_ci default NULL,
      PRIMARY KEY  (`id`),
      UNIQUE KEY `slug` (`slug`),
      KEY `public` (`public`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

	$db->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix}sections` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `title` varchar(255) collate utf8_unicode_ci default NULL,
      `description` text collate utf8_unicode_ci,
      `exhibit_id` int(10) unsigned NOT NULL,
      `order` tinyint(3) unsigned NOT NULL,
      `slug` varchar(30) collate utf8_unicode_ci NOT NULL,
      PRIMARY KEY  (`id`),
      KEY `slug` (`slug`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $db->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix}items_section_pages` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `item_id` int(10) unsigned default NULL,
      `page_id` int(10) unsigned NOT NULL,
      `text` text collate utf8_unicode_ci,
      `order` tinyint(3) unsigned NOT NULL,
      PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    
    $db->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix}section_pages` (
      `id` int(10) unsigned NOT NULL auto_increment,
      `section_id` int(10) unsigned NOT NULL,
      `title` varchar(255) collate utf8_unicode_ci NOT NULL,
      `slug` varchar(30) collate utf8_unicode_ci NOT NULL,
      `layout` varchar(255) collate utf8_unicode_ci default NULL,
      `order` tinyint(3) unsigned NOT NULL,
      PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

    $checkIfTitleExists = $db->fetchOne("SHOW COLUMNS FROM `{$db->prefix}section_pages` LIKE 'title'");
     
    if ($checkIfTitleExists == null) {
        $db->query("ALTER TABLE `{$db->prefix}section_pages` ADD `title` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL, ADD `slug` VARCHAR( 30 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;");

        $db = get_db();

        $newSectionPages = $db->fetchAll("SELECT * FROM `{$db->prefix}section_pages`");

        foreach($newSectionPages as $newPage) {
             $pageNum = $newPage['order'];
             $pageTitle = 'Page '. $pageNum;
             $slug = generate_slug($pageTitle); 
             $id = $newPage['id'];       

            $db->exec("UPDATE `{$db->prefix}section_pages` SET title='$pageTitle', slug='$slug' WHERE id='$id'");
            }
    }
}

/**
 * Modify the ACL to include an 'ExhibitBuilder_Exhibits' resource.  
 * 
 * Requires the module name as part of the ACL resource in order to avoid naming
 * conflicts with pre-existing controllers, e.g. an ExhibitBuilder_ItemsController
 * would not rely on the existing Items ACL resource.
 * 
 * NOTE: unless explicitly denied, super users and admins will have access to all
 * of the defined resources and privileges.  Other user levels will not by default.
 * That means that admin and super users can both manipulate exhibits completely,
 * but researcher/contributor cannot.
 * 
 * @return void
 **/
function exhibit_builder_setup_acl($acl)
{
    $acl->loadResourceList(array('ExhibitBuilder_Exhibits'=> array('add', 'edit',
    'delete', 'add-page', 'edit-page-content', 'edit-page-metadata', 'delete-page', 'add-section', 'edit-section',
    'delete-section', 'showNotPublic', 'section-list', 'page-list')));    
      
}

/**
 * Add the routes from routes.ini in this plugin folder.
 * 
 * @return void
 **/
function exhibit_builder_routes($router) 
{
     $router->addConfig(new Zend_Config_Ini(EXHIBIT_PLUGIN_DIR .
     DIRECTORY_SEPARATOR . 'routes.ini', 'routes'));
}

function exhibit_builder_public_header()
{
    if ($layoutCssHref = layout_css()) {
        // Add the stylesheet for the layout
        echo '<link rel="stylesheet" media="screen" href="' . $layoutCssHref . '" /> ';
    }
}

function exhibit_builder_admin_header($request)
{
    // Check if using Exhibits controller, and add the stylesheet for general display of exhibits   
    if ($request->getControllerName() == 'exhibits' || ($request->getModuleName() == 'default' && $request->getControllerName() == 'index' && $request->getActionName() == 'index')):
            echo '<link rel="stylesheet" media="screen" href="' . css('exhibits') . '" /> ';
    		echo js('tiny_mce/tiny_mce');
    		echo js('search'); 
    		echo js('exhibits');
        endif;

}

function exhibit_builder_dashboard()
{
?>
    <?php if(has_permission('ExhibitBuilder_Exhibits','browse')): ?>
	<dt class="exhibits"><a href="<?php echo uri('exhibits'); ?>">Exhibits</a></dt>
	<dd class="exhibits">
		<ul>
			<li><a class="browse-exhibits" href="<?php echo uri('exhibits'); ?>">Browse Exhibits</a></li>
			<li><a class="add-exhibit" href="<?php echo uri('exhibits/add/'); ?>">Create an Exhibit</a></li>
		</ul>
		<p>Create and manage exhibits that display items from the archive.</p>
	</dd>
	<?php endif;
}

function exhibit_builder_public_main_nav($navArray) {
    $navArray['Browse Exhibits'] = uri('exhibits');
    return $navArray;
}

function exhibit_builder_admin_nav($navArray)
{
    if (has_permission('ExhibitBuilder_Exhibits', 'browse')) {
        
        $navArray += array('Exhibits'=> uri('exhibits'));
    }
    
    return $navArray;
}

/**
 * Custom hook from the HtmlPurifier plugin that will only fire when that plugin is
 * enabled.
 * 
 * @param Zend_Controller_Request_Http $request
 * @param HTMLPurifier $purifier The purifier object that was built from the configuration
 * provided on the configuration form of the HtmlPurifier plugin.  
 * @return void
 **/
function exhibit_builder_purify_html($request, $purifier)
{
    // Make sure that we only bother with the Exhibits controller in the ExhibitBuilder module.
    if ($request->getControllerName() != 'exhibits' or $request->getModuleName() != 'exhibit-builder') {
        return;
    }
    
    $post = $request->getPost();
    
    switch ($request->getActionName()) {
        // exhibit-metadata-form
        case 'add':
        case 'edit':
        // section-metadata-form    
        case 'add-section':
        case 'edit-section':
            // The description field on both of these forms should be HTML.
            $post['description'] = $purifier->purify($post['description']);
            break;

        case 'add-page':
        case 'edit-page-metadata':
            // Skip the page-metadata-form.
            break;
        
        case 'edit-page-content':
            // page-content-form
            
            if (is_array($post['Text'])) {
                // All of the 'Text' entries are HTML.
                foreach ($post['Text'] as $key => $text) {
                    $post['Text'][$key] = $purifier->purify($text);
                }            
            }
                
            break;
        
        default:
            // Don't process anything by default.
            break;
    }
    
    $request->setPost($post);
}

