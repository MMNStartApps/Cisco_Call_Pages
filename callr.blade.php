@extends('layouts.site')

@section('content')

  <div class="register-box-body page_call">  

    <div class="row" style="margin-bottom:20px;">
      <div class="col-sm-12">
          <a href="/visits" class="btn btn-info">Annulla</a>
      </div>
      <div class="col-sm-12">
        
        <h3 id="status">Connessione in corso...</h3>

        <div style="display: flex">
          
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
      
    </div>
  
  </div>

@stop

@section('extra_css')

  <style>
    .page_call .video_box {
        width: 49%;
        border: 4px solid #b0b0b0;
        padding: 10px;
      }
      .page_call .video_box video {
        width: 99%;
      }      
  </style>

@stop

@section('extra_js')
    
    <script crossorigin src="https://unpkg.com/webex@^1/umd/webex.min.js"></script>
    <script>
        
        var webex = window.Webex.init({
            credentials: {
                access_token: '{{$token}}'
              }
            });

        var destination = '{{$visit->sip}}';

        var status = document.getElementById('status');
        var buttonClose = document.getElementById('hangup');

        var token = '{{$token}}';
        // wait until the SDK is loaded and ready
        webex.once('ready', function() {
            
            if (!webex.meetings.registered) {
                webex.meetings.register()
                  // Sync our meetings with existing meetings on the server
                  .then(() => webex.meetings.syncMeetings())
                  .then(() => {
                    // This is just a little helper for our selenium tests and doesn't
                    // really matter for the example
                    
                    // Our device is now connected
                    setTimeout(function(){ call(); }, 3000);
                  })
                  // This is a terrible way to handle errors, but anything more specific is
                  // going to depend a lot on your app
                  .catch((err) => {
                    console.error(err);
                    // we'll rethrow here since we didn't really *handle* the error, we just
                    // reported it
                    throw err;
                  });
              }
              else {
                // Device was already connected
                setTimeout(function(){ call(); }, 3000);
              }
            
        });

        function bindMeetingEvents(meeting) {
            meeting.on('error', (err) => {
              console.error(err);
              status.innerHTML = err;
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
              window.location.href = '/visits';
            });
          }
          
          // Join the meeting and add media
          function joinMeeting(meeting) {
          
            status.innerHTML = '';

            return meeting.join().then(() => {
              const mediaSettings = {
                receiveVideo: true,
                receiveAudio: true,
                receiveShare: false,
                sendVideo: true,
                sendAudio: true,
                sendShare: false
              };
          
              // Get our local media stream and add it to the meeting
              return meeting.getMediaStreams(mediaSettings).then((mediaStreams) => {
                const [localStream, localShare] = mediaStreams;
          
                meeting.addMedia({
                  localShare,
                  localStream,
                  mediaSettings
                });
              });
            });
          }

          function call()
          {
            return webex.meetings.create(destination).then((meeting) => {
              // Call our helper function for binding events to meetings
              bindMeetingEvents(meeting);
          
              status.innerHTML = 'Chiamata in corso...';
              buttonClose.style.display = 'block';

              return joinMeeting(meeting);
            })
            .catch((error) => {
              // Report the error
              status.innerHTML = error;
              console.error(error);
            });
          }
    </script>
@stop