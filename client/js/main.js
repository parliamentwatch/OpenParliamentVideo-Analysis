var isMouseOver = false;
var isStatusMouseOver = false;

$(function() {

	getQueue();


	$("#uploadProtocolInputFile").change(function() {
		if ($(this).val()) {
			$("#startUpload").removeAttr("disabled").prop("disabled",false);
			//$(this).next().after().text($(this).val().split('\\').slice(-1)[0]);
			var tmpText = "";
			for (var i in $(this)[0]["files"]) {
				//console.log(i);
				if ((i != "length") && (i != "item")) {
					tmpText += $(this)[0]["files"][i]["name"] + ", ";
				}
			}
			$(this).next().after().text(tmpText);
		} else {
			$("#startUpload").attr("disabled","disabled").prop("disabled",true);
			$(this).next().after().text("Choose file...");
		}
	});

	$("#updateQueue").click(function() {
		getQueue();
	});

	$("#errorsOnly").click(function() {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			$('#status').removeClass('errorsOnly');
		} else {
			$(this).addClass('active');
			$('#status').addClass('errorsOnly');
		}
	});

	$("#uploadProtocol").ajaxForm({
		url:"server/ajaxServer.php",
		data:{"a":"uploadProtocol"},
		"method":"POST",
		beforeSubmit: function() {
			if (!$("#uploadProtocolInputFile").val()) {
				//TODO: Inform user about missing xml
				return false;
			}
		},
		success: function(ret) {
			if (ret["status"] == "success") {
				//TODO: Inform user that files have been uploaded
				$("#uploadProtocol").trigger("reset");
				$("#uploadProtocolInputFile").trigger("change");
				getQueue();
			} else {
				//TODO: Inform user about error while file upload
				console.log("error occured on upload");
			}
		}
	});

	$(document).on("click", ".removeFromQueue", function() {
		$.ajax({
			url:"server/ajaxServer.php",
			data:{a:"removeFromQueue",item:$(this).data("key")},
			dataType:"json",
			success: function() {
				getQueue();
			}
		})
	});

	$(document).on("click", "#startQueue", function() {
		$('#status').empty();
		forceAlignXML();
	});

	$('a[data-toggle="tab"]').on("show.bs.tab", function(e) {
		if ($(e.target).attr("href") == "#outputContainer") {
			window.setTimeout(function() {
				getOutput();
			},1000);
		}
	});

	$('#transcript').hover(function() {
		isMouseOver = true;
	}, function() {
		isMouseOver = false;
	});

	$('#status').hover(function() {
		isStatusMouseOver = true;
	}, function() {
		isStatusMouseOver = false;
	});

	//getOutput()

});

function getQueue() {
	$.ajax({
		url:"server/ajaxServer.php",
		data:{a:"getQueue"},
		dataType:"json",
		success: function(ret) {
			if (ret["status"] == "success") {
				$("#queueContainer").html("");
				if (ret["items"].length > 0) {
					$(ret["items"]).each(function(k,v) {
						$("#queueContainer").append("<li class='list-group-item'>" +
						"	<button class='btn btn-outline-light text-info btn-sm removeFromQueue' data-key='"+k+"'>" +
						"		<i class='fas fa-trash-alt'></i>" +
						"	</button> "+v +
						"</li>");
					});
					$("#startQueue").removeAttr("disabled").prop("disabled",false);
				} else {
					$("#queueContainer").html("<li class='list-group-item'>Queue is empty</li>");
					$("#startQueue").attr("disabled","disabled").prop("disabled",true);
				}

			} else {
				$("#queueContainer").html("<li class='list-group-item'>Error while refreshing queue</li>");
				$("#startQueue").attr("disabled","disabled").prop("disabled",true);
			}
		},
		error: function() {
			$("#queueContainer").html("<li class='list-group-item'>Error while refreshing queue</li>");
			$("#startQueue").attr("disabled","disabled").prop("disabled",true);
		}
	});
}

function getOutput() {

	$.ajax({
		url:"output/index_media.json",
		dataType:"json",
		success: function(ret) {
			var tmpContent = $("<div></div>");
			$.each(ret, function(k,v) {
				//console.log(v);
				if (tmpContent.find("#wp_"+v["wahlperiode"]).length < 1) {
					tmpContent.append("<ul class='wahlperiode' id='wp_"+v["wahlperiode"]+"'>" +
					"	<li>Electoral Period "+v["wahlperiode"]+"</li>" +
					"	<ul class='children'></ul>" +
					"</ul>");
				}

				if (tmpContent.find("#wp_"+v["wahlperiode"]+"_sn_"+v["sitzungsnummer"]).length < 1) {
					tmpContent.find("#wp_"+v["wahlperiode"]+" > ul.children").append("" +
					"<ul class='sitzungsnummer' id='wp_"+v["wahlperiode"]+"_sn_"+v["sitzungsnummer"]+"' data-sitzungsnummer='"+v["sitzungsnummer"]+"' data-wp='"+v["wahlperiode"]+"'>" +
					"	<li>Meeting "+v["sitzungsnummer"]+"</li>" +
					"	<ul class='children'></ul>" +
					"</ul>");
				}

				if (tmpContent.find("#wp_"+v["wahlperiode"]+"_sn_"+v["sitzungsnummer"]+" ul.tagesordnungspunkt[data-top='"+v["top"]+"']").length < 1) {
					tmpContent.find("#wp_"+v["wahlperiode"]+"_sn_"+v["sitzungsnummer"]+" > ul.children").append("" +
					"<ul class='tagesordnungspunkt' data-top='"+v["top"]+"'>" +
					"	<li>Agenda Item: "+v["top"]+"</li>" +
					"	<ul class='children'></ul>" +
					"</ul>");
				}
				tmpContent.find("#wp_"+v["wahlperiode"]+"_sn_"+v["sitzungsnummer"]+" ul.tagesordnungspunkt[data-top='"+v["top"]+"'] > .children").append("" +
				"<li " +
				"		class='rede'" +
				"		data-id='"+v["id"]+"'" +
				"		data-top='"+v["top"]+"'" +
				"		data-sn='"+v["sitzungsnummer"]+"'" +
				"		data-wp='"+v["wahlperiode"]+"'" +
				"		data-mediaid='"+v["mediaID"]+"'" +
				"		data-rednerid='"+v["rednerID"]+"'>" +
				"	Speech ID: "+v["id"]+", Speaker ID: "+v["rednerID"]+", Media ID: "+ v["mediaID"] +
				"</li>")


			});
			$("#fileContents").html(tmpContent);
		},
		error: function() {
			$("#fileContents").html("No media in admins output database.");
		}
	});

	$("#fileContents").on("click", ".rede", function() {
		//return;
		var videoPath = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'+$(this).data("mediaid")+'/'+$(this).data("mediaid")+'_h264_1920_1080_5000kb_baseline_de_5000.mp4';

		var htmlURL = "output/"+$(this).data("wp")+"/"+$(this).data("sn").pad(3)+"/"+$(this).data("wp")+$(this).data("sn").pad(3)+"-Rede-"+$(this).data("id")+".html";

		generatePreview(videoPath,htmlURL);
	});

	$("#fileContents").on("click", "ul, li", function() {
		var childContainer = $(this).siblings(".children");
		childContainer.stop();
		if (!childContainer.hasClass("tmp-visible")) {
			childContainer.slideDown(function() {
				$(this).addClass("tmp-visible");
				childContainer.css("height","");
			});
		} else {
			childContainer.slideUp(function() {
				$(this).removeClass("tmp-visible");
			});
		}
	})

	$('#transcript').on("click", ".timebased", function(){
		$('#video video')[0].currentTime = parseFloat($(this).attr('data-start'));
	})


}
var tmp;
//function generatePreview(videoURL, htmlString) {
function generatePreview(videoURL, htmlURL) {

	var videoElem = $('<video src="'+ videoURL +'" type="video/mp4" controls/>');

	videoElem.on('timeupdate', checkTimings);

	$('#video').html(videoElem);

	$('#transcript').load(htmlURL+" div.rede");

}

function checkTimings() {

	var currentTime = $('#video video')[0].currentTime;

	var timebasedElements = $('#transcript').find('.timebased');

	if ( timebasedElements.length != 0 ) {
		timebasedElements.each(function() {
			var startTime = parseFloat($(this).attr('data-start')),
				endTime = parseFloat($(this).attr('data-end'));
			if ( startTime-0.5 <= currentTime && endTime-0.5 >= currentTime ) {
				if ( !$(this).hasClass('active') ) {
					$(this).addClass('active');
					scrollTimebasedElements();
				}
			} else if ( $(this).hasClass('active') ) {
				$(this).removeClass('active');
			}
		});
	}

}

function scrollTimebasedElements() {

	if (isMouseOver) {
		return;
	}
	var customhtmlContainer = $('#transcript'),
		firstActiveElement = customhtmlContainer.find('.timebased.active').eq(0);


	if ( firstActiveElement.length == 0 ) {
		return;
	}

	var activeElementPosition = firstActiveElement.position();

	if ( activeElementPosition.top <
		customhtmlContainer.height()/2 + customhtmlContainer.scrollTop()
		|| activeElementPosition.top > customhtmlContainer.height()/2 + customhtmlContainer.scrollTop() ) {

		var newPos = activeElementPosition.top + customhtmlContainer.scrollTop() - customhtmlContainer.height()/2;
		customhtmlContainer.stop().animate({scrollTop : newPos},400);
	}


}

function forceAlignXML() {

	if (!window.XMLHttpRequest){
		console.log('Browser does not support native XMLHttpRequest.');
		return;
	}
	try {
		var xhr = new XMLHttpRequest();  
		xhr.previous_text = '';
									 
		xhr.onerror = function() { 
			staticFallback();
		};
		xhr.onreadystatechange = function() {
			try {
				if (xhr.readyState == 4){
					getQueue();
					$('#speechStatus').html('<span class="success">Finished indexing queue</span>');
				} 
				else if (xhr.readyState > 2){
					//console.log(xhr.responseText);

					var new_response = xhr.responseText.substring(xhr.previous_text.length);                    
					
					var new_response_parts = new_response.split('{');

					for (var i=0; i<new_response_parts.length; i++) {

						if (new_response_parts[i].length == 0) {
							continue;
						}

						//console.log('{'+ new_response_parts[i]);

						var result = JSON.parse('{'+ new_response_parts[i]);

						
						if (result.video && result.html) {
							//generatePreview(result.video, result.html);
							//autoProcessNextItem();
						}

						if ((result.task != 'speechStatus') && (result.task != 'console') && ((result.task != 'download') || (result.task == 'download' && result.progress == 100 && result.status == 'success') || (result.task == 'download' && result.status == 'error'))) {
							$('#status')[0].innerHTML += '<div class="'+ result.status +'">'+ result.message +'</div>';
							if (!isStatusMouseOver) {
								$('#status')[0].scrollTop = $('#status')[0].scrollHeight;
							}
						}

						if (result.task == 'console') {
							console.log(result.console);
						}						

						if (result.task == 'speechStatus') {
							$('#speechStatus').html('<span class="'+result.status+'">'+ result.message +'</span>');
							getQueue();
						}

						if (result.task == 'startDownload') {
							$('#status')[0].innerHTML += '<div class="progressContainer download"><div class="progressIndicator"></div></div>';
							if (!isStatusMouseOver) {
								$('#status')[0].scrollTop = $('#status')[0].scrollHeight;
							}
						}

						if (result.task == 'startForceAlign') {
							$('#status')[0].innerHTML += '<div class="progressContainer forcealign"><div class="progressIndicator forcealign"></div></div>';
							
							if (!isStatusMouseOver) {
								$('#status')[0].scrollTop = $('#status')[0].scrollHeight;
							}

							$('#status .progressContainer.forcealign').last().children('.progressIndicator').width('60%');
						}
						
						if (result.task == 'download') {
							if (result.status == 'error') {
								$('#status .progressContainer.download').last().children('.progressIndicator').addClass('error');
							} else if (result.progress == 100) {
								$('#status .progressContainer.download').last().children('.progressIndicator').addClass('success');
							}
							$('#status .progressContainer.download').last().children('.progressIndicator').width(result.progress + '%');
							
						} else if (result.task == 'forcealign') {
							$('#status .progressContainer.forcealign').last().children('.progressIndicator').width(result.progress + '%');
							if (result.progress == 100) {
								$('#status .progressContainer.forcealign').last().children('.progressIndicator').addClass('success');
							}
							if (result.status == 'error') {
								$('#status .progressContainer.forcealign').last().children('.progressIndicator').addClass('error');
							}
						}
					}

					xhr.previous_text = xhr.responseText;
				}  
			}
			catch (e){
				console.log('[XHR STATECHANGE] Exception: ' + e);
				//staticFallback();
			}                     
		};
		xhr.open('GET', 'server/ajaxServer.php?a=processQueue');
		xhr.send();
	}
	catch (e){
		console.log('[XHR REQUEST] Exception: ' + e);
		xhr.onerror();
	}

}

Number.prototype.pad = function(size) {
	var s = String(this);
	while (s.length < (size || 2)) {s = "0" + s;}
	return s;
}