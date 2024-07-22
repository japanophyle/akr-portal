<!DOCTYPE html>
<html class="no-js" lang="en" prefix="og: http://ogp.me/ns#">
	<head>
		<base href="{base_ref}" />
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{title}</title>
		<link rel="stylesheet" href="assets/css/gbm.min.css" />

		<style>
			#poster{width:350px;height:400px;}
		</style>
    </head>
    <body>
        <div id = "main" class="row">
			<div class="small-12 columns">
			  {body}
			</div>     
        </div>
        <script src="assets/js/jamslim.min.js?v=4"></script>
 		<script>
			$(document).foundation();
			{scripts} 
		</script>		
    </body>    
</html>
