amplify.request.decoders.errorDecoder =
    function (data, status, xhr, success, error) {
    	if (status == "success") {
    		success(data, xhr.status);
    	} else {
    		error($.parseJSON(xhr.responseText), xhr.status);
    	}
    };

amplify.request.define(
	"hasPassword", "ajax",
	{
		url: "api/index.php?path=/hasPassword",
		type: "GET",
		dataType: "json",
		cache: false,
		decoder: "errorDecoder"
	}
);

amplify.request.define(
	"checkPassword", "ajax",
	{
		url: "api/index.php?path=/checkPassword",
		type: "GET",
		dataType: "json",
		cache: false,
		decoder: "errorDecoder"
	}
);