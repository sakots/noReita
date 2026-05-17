<div class="thfoot">
  @if ($share_button)
    @if ($use_misskey_note)
      <span class="button"><a href="{{$self}}?mode=before_misskey_note&amp;no={{$bbsline['tid']}}">
        <span class="simple-icons--misskey"></span> Misskeyにノート</a>
      </span>
    @endif
    @if ($switch_sns)
      <span class="button"><a href="{{$self}}?mode=set_share_server&amp;encoded_t={{$bbsline['encoded_t']}}&amp;encoded_u={{$bbsline['encoded_u']}}" onClick="open_sns_server_window(event,600,600)">
        <span class="eva--share-outline"></span> SNSで共有する</a>
      </span>
    @else
      <span class="button"><a href="https://x.com/intent/tweet?&amp;text=%5B{{$bbsline['tid']}}%5D%20{{$bbsline['sub']}}%20by%20{{$bbsline['a_name']}}%20-%20{{$board_title}}&amp;url={{$base}}{{$self}}?mode=res%26res={{$bbsline['tid']}}" target="_blank">
        <span class="ri--twitter-x-line"></span> tweet</a>
      </span>
    @endif
  @endif
  @if ($elapsed_time === 0 || $nowtime - $bbsline['past'] < $elapsed_time)
    <span class="button"><a href="{{$self}}?mode=res&amp;res={{$bbsline['tid']}}"><span class="ic--baseline-reply"></span> 返信</a></span>
  @else
    このスレは古いので返信できません…
  @endif
  <a href="#header">[↑]</a>
  <hr>
</div>