jQuery(function($){
	const $preview=$('#emo-preview');
	function val(name,fallback=''){const $el=$('[name="'+name+'"]');return $el.length?($el.val()||fallback):fallback;}
	function checked(name){return $('[name="'+name+'"]').is(':checked');}
	function preview(){
		if(!$preview.length)return;
		const t=val('template','classic');
		$preview.attr('class','emo-form emo-template-'+t)
			.css({'--emo-accent':val('accent_color','#635bff'),'--emo-bg':val('background_color','#ffffff'),'--emo-text':val('text_color','#172033'),'--emo-radius':parseInt(val('border_radius',14),10)+'px'});
		$preview.find('h3').text(val('title','Join our newsletter'));
		$preview.find('.emo-description').text(val('description',''));
		$preview.find('button span').text(val('button_text','Subscribe'));
		$preview.find('.preview-first').toggle(checked('show_first'));
		$preview.find('.preview-last').toggle(checked('show_last'));
		$preview.find('.preview-phone').toggle(checked('show_phone'));
		$preview.find('.preview-gdpr').toggle(checked('gdpr')).find('span').text(val('gdpr_text',''));
	}
	$('body').on('input change','.emo-live',preview);
	preview();

	$('#emo-test-connection').on('click',function(){const $b=$(this),$r=$('#emo-test-result');$b.prop('disabled',true);$r.text(' Testing…');$.post(emoAdmin.ajax,{action:'emo_test_connection',nonce:emoAdmin.nonce}).done(function(res){$r.text(' '+(res.data||''));}).fail(function(){$r.text(' Connection test failed.');}).always(function(){$b.prop('disabled',false);});});
	$('.emo-delete').on('click',function(){return window.confirm('Delete this form?');});

	function renderSchema(data){
		const savedGroups=(window.emoFormData&&emoFormData.savedGroups)||{};
		const savedMap=(window.emoFormData&&emoFormData.savedMap)||{};
		let groups='<h3>Interest groups</h3>';
		if(!data.groups.length)groups+='<p class="description">This audience has no interest groups.</p>';
		data.groups.forEach(function(cat){groups+='<div class="emo-option-box"><strong>'+escapeHtml(cat.title)+'</strong><div class="emo-checks">';cat.interests.forEach(function(item){groups+='<label><input type="checkbox" name="groups['+item.id+']" value="1" '+(savedGroups[item.id]?'checked':'')+'> '+escapeHtml(item.name)+'</label>';});groups+='</div></div>';});
		$('#emo-groups').html(groups);
		let merge='<h3>Merge field mapping</h3><p class="description">Map optional Mailchimp fields to submitted form values.</p>';
		if(!data.merge_fields.length)merge+='<p class="description">No additional merge fields found.</p>';
		data.merge_fields.forEach(function(field){if(['EMAIL','FNAME','LNAME','PHONE'].includes(field.tag))return;merge+='<div class="emo-map-row"><span>'+escapeHtml(field.name)+' <code>'+escapeHtml(field.tag)+'</code></span><select name="field_map['+escapeHtml(field.tag)+']"><option value="">Do not map</option><option value="first_name" '+(savedMap[field.tag]==='first_name'?'selected':'')+'>First name</option><option value="last_name" '+(savedMap[field.tag]==='last_name'?'selected':'')+'>Last name</option><option value="phone" '+(savedMap[field.tag]==='phone'?'selected':'')+'>Phone</option></select></div>';});
		$('#emo-merge-fields').html(merge);
	}
	function escapeHtml(s){return $('<div>').text(s||'').html();}
	function loadSchema(){const id=$('#emo-audience').val();if(!id){$('#emo-groups').html('<p class="description">Select an audience to load its interest groups.</p>');$('#emo-merge-fields').html('<p class="description">Audience merge fields will appear here.</p>');return;}$('#emo-groups,#emo-merge-fields').html('<p class="description">Loading audience fields…</p>');$.post(emoAdmin.ajax,{action:'emo_audience_schema',nonce:emoAdmin.nonce,audience_id:id}).done(function(res){if(res.success)renderSchema(res.data);else $('#emo-groups,#emo-merge-fields').html('<p class="description">Could not load audience fields.</p>');});}
	$('#emo-audience').on('change',loadSchema);
	if($('#emo-audience').val())loadSchema();
});