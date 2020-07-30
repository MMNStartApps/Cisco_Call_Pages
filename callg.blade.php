@extends('layouts.frontend')

@section('content')

  <div class="register-box-body page_call">  

    <div class="row" style="margin-bottom:20px;">
      <div class="col-sm-12">
        <a href="/visite" class="btn btn-primary btn-back">Torna alle visite</a>
      </div>
      <div class="col-sm-12">
        
        <h3 id="status">Connessione in corso...</h3>

        <div class="video_container">
          
          <div class="video_box">
            <video id="self-view" muted autoplay></video>
          </div>
          <div class="video_box" style="margin-left:1%">
            <audio id="remote-view-audio" autoplay></audio>
            <video id="remote-view-video" autoplay></video>
          </div>
        </div>

        <button id="hangup" title="hangup" type="button" class="btn btn-w-m btn-primary" style="display:none">Chiudi chiamata</button>

      </div>

      <div class="col-sm-12">
      <form id="constraints">
        <fieldset>
          <legend>Funzioni:</legend>
          <input id="constraints-audio" title="audio" type="checkbox" checked>
          <label for="constraints-audio">Audio</label>
          <input id="constraints-video" title="video" type="checkbox" checked>
          <label for="constraints-video">Video</label>
        </fieldset>
      </form>
      </div>
      
    </div>
  
  </div>
    
    

@stop

@section('extra_js')
    
    <script crossorigin src="https://unpkg.com/webex@^1/umd/webex.min.js"></script>
    <script>
        
        let x = new RTCPeerConnection();
        x.createOffer();

        var webex = window.Webex.init({
          logger: {
            level: 'debug'
          },
          meetings: {
            reconnection: {
              enabled: true
            }
          }
        });

        var destination = '{{$visit->sip}}';

        var status = document.getElementById('status');
        var buttonClose = document.getElementById('hangup');

        var token = '{{$jwt}}';
        token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJZMmx6WTI5emNHRnlhem92TDNWekwwOVNSMEZPU1ZwQlZFbFBUaTgzWWpFMk56UmlaUzA0TlRrMkxUUXpObUl0T1dJNU5pMDROMkkzTVRBek9HUTFOemsiLCJzdWIiOiJwYXppZW50ZS1mYmYtMTIzIiwibmFtZSI6IlBhemllbnRlIEZCRiJ9.1leFku25o-yMmBbHlCjBn1chCnxKs_c_mSacci0XDwg';
        
        // wait until the SDK is loaded and ready
        webex.once('ready', function() {
            webex.authorization.requestAccessTokenFromJwt({jwt: token})
            .then(() => {
                
                if (webex.meetings.register()
                .then(() => webex.meetings.syncMeetings())
                .catch((err) => {
                    console.error(err);
                    document.getElementById("status").innerHTML = err;
                    throw err;
                }))
                {
                  setTimeout(function(){ call(); }, 15000);  
                }

            })
        });

        function bindMeetingEvents(meeting) {
            meeting.on('error', (err) => {
              console.error(err);
              document.getElementById("status").innerHTML = err;
            });
          
            // Handle media streams changes to ready state
            meeting.on('media:ready', (media) => {
              if (!media) {
                return;
              }
              if (media.type === 'local') {
                document.getElementById('self-view').srcObject = media.stream;
              }
              if (media.type === 'remoteVideo') {
                document.getElementById('remote-view-video').srcObject = media.stream;
              }
              if (media.type === 'remoteAudio') {
                document.getElementById('remote-view-audio').srcObject = media.stream;
              }
            });
          
            // Handle media streams stopping
            meeting.on('media:stopped', (media) => {
              // Remove media streams
              if (media.type === 'local') {
                document.getElementById('self-view').srcObject = null;
              }
              if (media.type === 'remoteVideo') {
                document.getElementById('remote-view-video').srcObject = null;
              }
              if (media.type === 'remoteAudio') {
                document.getElementById('remote-view-audio').srcObject = null;
              }
            });
            
            // Of course, we'd also like to be able to leave the meeting:
            document.getElementById('hangup').addEventListener('click', () => {
              meeting.leave();
              window.location.href = '/visite';
            });
          }

          function joinMeeting(meeting) {

            document.getElementById("status").innerHTML = '';

            // Get constraints
            const constraints = {
              audio: document.getElementById('constraints-audio').checked,
              video: document.getElementById('constraints-video').checked
            };

            return meeting.join().then(() => {
              return meeting.getSupportedDevices({
                sendAudio: constraints.audio,
                sendVideo: constraints.video
              })
                .then(({sendAudio, sendVideo}) => {
                  const mediaSettings = {
                    receiveVideo: constraints.video,
                    receiveAudio: constraints.audio,
                    receiveShare: false,
                    sendShare: false,
                    sendVideo,
                    sendAudio
                  };

                  return meeting.getMediaStreams(mediaSettings).then((mediaStreams) => {
                    const [localStream, localShare] = mediaStreams;

                    meeting.addMedia({
                      localShare,
                      localStream,
                      mediaSettings
                    });
                  });
                });
            });
          }

          function call()
          {
            return webex.meetings.create(destination).then((meeting) => {
              // Call our helper function for binding events to meetings
              bindMeetingEvents(meeting);

              document.getElementById("status").innerHTML = 'Chiamata in corso...';
              buttonClose.style.display = 'block';
              return joinMeeting(meeting);
            })
            .catch((error) => {
              // Report the error
              if (confirm("ATTENZIONE : chiamata non ancora iniziata dal medico, chiudere e riprovare tra qualche minuto")) {
                document.location.href='/visite';
              }
              document.getElementById("status").innerHTML = '';
             
            });
          }

          // Use enumerateDevices API to check/uncheck and disable checkboxex (if necessary)
          // for Audio and Video constraints
          window.addEventListener('load', () => {
            // Get elements from the DOM
            const audio = document.getElementById('constraints-audio');
            const video = document.getElementById('constraints-video');

            // Get access to hardware source of media data
            // For more info about enumerateDevices: https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/enumerateDevices
            if (navigator && navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
              navigator.mediaDevices.enumerateDevices()
                .then((devices) => {
                  // Check if navigator has audio
                  const hasAudio = devices.filter(
                    (device) => device.kind === 'audioinput'
                  ).length > 0;

                  // Check/uncheck and disable checkbox (if necessary) based on the results from the API
                  audio.checked = hasAudio;
                  audio.disabled = !hasAudio;

                  // Check if navigator has video
                  const hasVideo = devices.filter(
                    (device) => device.kind === 'videoinput'
                  ).length > 0;

                  // Check/uncheck and disable checkbox (if necessary) based on the results from the API
                  video.checked = hasVideo;
                  video.disabled = !hasVideo;
                })
                .catch((error) => {
                  // Report the error
                  console.error(error);
                });
            }
            else {
              // If there is no media data, automatically uncheck and disable checkboxes
              // for audio and video
              audio.checked = false;
              audio.disabled = true;

              video.checked = false;
              video.disabled = true;
            }
          });
    </script>
@stop