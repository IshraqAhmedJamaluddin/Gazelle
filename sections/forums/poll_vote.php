<?php

$threadId = (int)$_POST['topicid'];
if ($threadId < 1) {
    error(0, true);
}

$forum = new \Gazelle\Forum();
$ForumID = $forum->setForumFromThread($threadId);

list($Question, $Answers, $Votes, $Featured, $Closed) = $forum->pollData($threadId);
if ($Closed) {
    error(403,true);
}

if (!empty($Votes)) {
    $TotalVotes = array_sum($Votes);
    $MaxVotes = max($Votes);
} else {
    $TotalVotes = 0;
    $MaxVotes = 0;
}

if (!isset($_POST['vote']) || !is_number($_POST['vote'])) {
?>
<span class="error">Please select an option.</span><br />
<form class="vote_form" name="poll" id="poll" action="">
    <input type="hidden" name="action" value="poll" />
    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
    <input type="hidden" name="large" value="<?=display_str($_POST['large'])?>" />
    <input type="hidden" name="topicid" value="<?=$threadId?>" />
<?php
    for ($i = 1, $il = count($Answers); $i <= $il; $i++) { ?>
    <input type="radio" name="vote" id="answer_<?=$i?>" value="<?=$i?>" />
    <label for="answer_<?=$i?>"><?=display_str($Answers[$i])?></label><br />
<?php
    } ?>
    <br /><input type="radio" name="vote" id="answer_0" value="0" /> <label for="answer_0">Blank&#8202;&mdash;&#8202;Show the results!</label><br /><br />
    <input type="button" onclick="ajax.post('index.php', 'poll', function(response) { $('#poll_container').raw().innerHTML = response });" value="Vote" />
</form>
<?php
} else {
    authorize();
    $Vote = $_POST['vote'];
    if (!isset($Answers[$Vote]) && $Vote != 0) {
        error(0,true);
    }

    //Add our vote
    if ($forum->addPollVote($LoggedUser['ID'], $threadId, $Vote)) {
        $Votes[$Vote]++;
        $TotalVotes++;
        $MaxVotes++;
    }

    if ($Vote != 0) {
        $Answers[$Vote] = '=> '.$Answers[$Vote];
    }
?>
        <ul class="poll nobullet">
<?php
        if ($ForumID != STAFF_FORUM) {
            for ($i = 1, $il = count($Answers); $i <= $il; $i++) {
                if (!empty($Votes[$i]) && $TotalVotes > 0) {
                    $Ratio = $Votes[$i] / $MaxVotes;
                    $Percent = $Votes[$i] / $TotalVotes;
                } else {
                    $Ratio = 0;
                    $Percent = 0;
                }
?>
                    <li><?=display_str($Answers[$i])?> (<?=number_format($Percent * 100, 2)?>%)</li>
                    <li class="graph">
                        <span class="left_poll"></span>
                        <span class="center_poll" style="width: <?=number_format($Ratio * 100, 2)?>%;"></span>
                        <span class="right_poll"></span>
                    </li>
<?php
            }
        } else {
            //Staff forum, output voters, not percentages
            $DB->query("
                SELECT GROUP_CONCAT(um.Username SEPARATOR ', '),
                    fpv.Vote
                FROM users_main AS um
                    JOIN forums_polls_votes AS fpv ON um.ID = fpv.UserID
                WHERE TopicID = $threadId
                GROUP BY fpv.Vote");

            $StaffVotes = $DB->to_array();
            foreach ($StaffVotes as $StaffVote) {
                list($StaffString, $StaffVoted) = $StaffVote;
?>
                <li><a href="forums.php?action=change_vote&amp;threadid=<?=$threadId?>&amp;auth=<?=$LoggedUser['AuthKey']?>&amp;vote=<?=(int)$StaffVoted?>"><?=display_str(empty($Answers[$StaffVoted]) ? 'Blank' : $Answers[$StaffVoted])?></a> - <?=$StaffString?></li>
<?php
            }
        }
?>
        </ul>
        <br /><strong>Votes:</strong> <?=number_format($TotalVotes)?>
<?php
}
