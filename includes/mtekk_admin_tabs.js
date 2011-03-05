jQuery(function()
{
	mtekk_admin_import_export_init();
	mtekk_admin_tabulator_init();
});
/**
 * Tabulator Bootup
 */
function mtekk_admin_tabulator_init(){
	if (!jQuery("#hasadmintabs").length) return;
	/* init markup for tabs */
	jQuery('#hasadmintabs').prepend("<ul><\/ul>");
	jQuery('#hasadmintabs > fieldset').each(function(i){
		id = jQuery(this).attr('id');
		caption = jQuery(this).find('h3').text();
		jQuery('#hasadmintabs > ul').append('<li><a href="#'+id+'"><span>'+caption+"<\/span><\/a><\/li>");
		jQuery(this).find('h3').hide();
	});
	/* init the tabs plugin */
	jQuery("#hasadmintabs").tabs();
	/* handler for opening the last tab after submit (compability version) */
	jQuery('#hasadmintabs ul a').click(function(i){
		var form   = jQuery('#llynx-options');
		var action = form.attr("action").split('#', 1) + jQuery(this).attr('href');
		form.get(0).setAttribute("action", action);
	});
}
/**
 * context screen options for import/export
 * TODO: Add in support for WordPress
 */
function mtekk_admin_import_export_init(){
	if (!jQuery('#mtekk_admin_import_export_relocate').length) return;
	jQuery('#screen-meta').prepend('<div id="screen-options-wrap" class="hidden"></div>');
	jQuery('#screen-meta-links').append(
		'<div id="screen-options-link-wrap" class="hide-if-no-js screen-meta-toggle">' +
		'<a class="show-settings" id="show-settings-link" href="#screen-options">' + objectL10n.import + '/' + objectL10n.export + '/' + objectL10n.reset +'</a></div>'
	);
	var code = jQuery('#mtekk_admin_import_export_relocate').html();
	jQuery('#mtekk_admin_import_export_relocate').html('');
	code = code.replace(/h3>/gi, 'h5>');
	jQuery('#screen-options-wrap').prepend(code);
}