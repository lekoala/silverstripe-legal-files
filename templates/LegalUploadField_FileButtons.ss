<% if $canDelete %>
<button type="button" data-href="$UploadFieldDeleteLink" class="ss-uploadfield-item-delete ss-ui-button ui-corner-all" title="<%t UploadField.DELETEINFO 'Permanently delete this file from the file store' %>" data-icon="minus-circle"><%t UploadField.DELETE 'Delete from files' %></button>
<% end_if %>