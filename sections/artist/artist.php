<?php
//~~~~~~~~~~~ Main artist page ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

$ArtistID = $_GET['id'];
if (!is_number($ArtistID)) {
    error(0);
}

if (empty($_GET['revisionid'])) {
    $RevisionID = false;
} else {
    if (!is_number($_GET['revisionid'])) {
        error(0);
    }
    $RevisionID = $_GET['revisionid'];
}

//----------------- Build list and get stats

try {
    $Artist = new \Gazelle\Artist($ArtistID, $RevisionID);
}
catch (\Exception $e) {
    error(404);
}

$Artist->loadArtistRole();
$Artist->loadGroups(Torrents::get_groups($Artist->groupIds(), true, true));

if ($Artist->hasRole(ARTIST_GUEST)) {
    $ReleaseTypes[1024] = 'Guest Appearance';
}
if ($Artist->hasRole(ARTIST_REMIXER)) {
    $ReleaseTypes[1023] = 'Remixed By';
}
if ($Artist->hasRole(ARTIST_COMPOSER)) {
    $ReleaseTypes[1022] = 'Composition';
}
if ($Artist->hasRole(ARTIST_PRODUCER)) {
    $ReleaseTypes[1021] = 'Produced By';
}

function sectionTitle ($id) {
    global $ReleaseTypes;
    switch ($ReleaseTypes[$id]) {
        case 'Anthology':
            $title = 'Anthologies';
            break;
        case 'DJ Mix':
            $title = 'DJ Mixes';
            break;
        case 'Produced By':
        case 'Remixed By':
            $title = $ReleaseTypes[$id];
            break;
        case 'Remix':
            $title = 'Remixes';
            break;
        default:
            $title = $ReleaseTypes[$id].'s';
            break;
    }
    return $title;
}

function torrentEdition($title, $year, $recordLabel, $catlogueNumber, $media) {
    return implode('::', [$title, $year, $recordLabel, $catlogueNumber, $media]);
}

$User = new \Gazelle\User($LoggedUser['ID']);
$name = $Artist->name() ?? 'artist:' . $ArtistID;
$Requests = [];

View::show_header($name, 'browse,requests,bbcode,comments,voting,subscriptions');
?>
<div class="thin">
    <div class="header">
        <h2><?=display_str($name)?><?= $RevisionID ? " (Revision #$RevisionID)" : '' ?><?= $Artist->vanityHouse() ? ' [Vanity House]' : '' ?></h2>
        <div class="linkbox">
<?php if (check_perms('site_edit_wiki')) { ?>
            <a href="artist.php?action=edit&amp;artistid=<?= $ArtistID ?>" class="brackets">Edit</a>
<?php } ?>
            <a href="artist.php?action=editrequest&amp;artistid=<?=$ArtistID?>" class="brackets">Request an Edit</a>
<?php if (check_perms('site_submit_requests')) { ?>
            <a href="requests.php?action=new&amp;artistid=<?=$ArtistID?>" class="brackets">Add request</a>
<?php
}

if (check_perms('site_torrents_notify')) {
    $urlStem = sprintf('artist.php?artistid=%d&amp;auth=%s', $ArtistID, $LoggedUser['AuthKey']);
    if ($User->hasArtistNotification($name)) {
?>
            <a href="<?= $urlStem ?>&amp;action=notifyremove" class="brackets">Do not notify of new uploads</a>
<?php } else { ?>
            <a href="<?= $urlStem ?>&amp;action=notify" class="brackets">Notify of new uploads</a>
<?php
    }
}

$bookmark = new \Gazelle\Bookmark;
if ($bookmark->isArtistBookmarked($LoggedUser['ID'], $ArtistID)) { ?>
            <a href="#" id="bookmarklink_artist_<?= $ArtistID ?>" onclick="Unbookmark('artist', <?= $ArtistID ?>, 'Bookmark'); return false;" class="brackets">Remove bookmark</a>
<?php } else { ?>
            <a href="#" id="bookmarklink_artist_<?= $ArtistID ?>" onclick="Bookmark('artist', <?= $ArtistID ?>, 'Remove bookmark'); return false;" class="brackets">Bookmark</a>
<?php } ?>
            <a href="#" id="subscribelink_artist<?= $ArtistID ?>" class="brackets" onclick="SubscribeComments('artist', <?=
                $ArtistID ?>);return false;"><?=
                (new Gazelle\Manager\Subscription($LoggedUser['ID']))->isSubscribedComments('artist', $ArtistID) !== false
                    ? 'Unsubscribe' : 'Subscribe'?></a>

            <a href="artist.php?action=history&amp;artistid=<?= $ArtistID ?>" class="brackets">View history</a>
<?php if ($RevisionID && check_perms('site_edit_wiki')) { ?>
            <a href="artist.php?action=revert&amp;artistid=<?=$ArtistID?>&amp;revisionid=<?=$RevisionID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Revert to this revision</a>
<?php } ?>
            <a href="artist.php?id=<?=$ArtistID?>#info" class="brackets">Info</a>
<?php if (defined('LASTFM_API_KEY')) { ?>
            <a href="artist.php?id=<?=$ArtistID?>#concerts" class="brackets">Concerts</a>
<?php } ?>
            <a href="artist.php?id=<?=$ArtistID?>#artistcomments" class="brackets">Comments</a>
<?php if (check_perms('site_delete_artist') && check_perms('torrents_delete')) { ?>
            <a href="artist.php?action=delete&amp;artistid=<?=$ArtistID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="brackets">Delete</a>
<?php } ?>
        </div>
    </div>

    <div class="sidebar">
<?php if ($Artist->image()) { ?>
        <div class="box box_image">
            <div class="head"><strong><?= $name ?></strong></div>
            <div style="text-align: center; padding: 10px 0px;">
                <img style="max-width: 220px;" src="<?= ImageTools::process($Artist->image(), true) ?>" alt="<?= $name?>" onclick="lightbox.init('<?= ImageTools::process($Artist->image()) ?>', 220);" />
            </div>
        </div>
<?php } ?>

        <div class="box box_search">
            <div class="head"><strong>File Lists Search</strong></div>
            <ul class="nobullet">
                <li>
                    <form class="search_form" name="filelists" action="torrents.php">
                        <input type="hidden" name="artistname" value="<?= $name ?>" />
                        <input type="hidden" name="action" value="advanced" />
                        <input type="text" autocomplete="off" id="filelist" name="filelist" size="20" />
                        <input type="submit" value="&rsaquo;" />
                    </form>
                </li>
            </ul>
        </div>

<?php

if (check_perms('zip_downloader')) {
    if (isset($LoggedUser['Collector'])) {
        list($ZIPList, $ZIPPrefs) = $LoggedUser['Collector'];
        $ZIPList = explode(':', $ZIPList);
    } else {
        $ZIPList = ['00', '11'];
        $ZIPPrefs = 1;
    }
?>
        <div class="box box_zipdownload">
            <div class="head colhead_dark"><strong>Collector</strong></div>
            <div class="pad">
                <form class="download_form" name="zip" action="artist.php" method="post">
                    <input type="hidden" name="action" value="download" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
                    <ul id="list" class="nobullet">
<?php
    foreach ($ZIPList as $ListItem) { ?>
                        <li id="list<?=$ListItem?>">
                            <input type="hidden" name="list[]" value="<?=$ListItem?>" />
                            <span style="float: left;"><?=$ZIPOptions[$ListItem]['2']?></span>
                            <span class="remove remove_collector"><a href="#" onclick="remove_selection('<?=$ListItem?>'); return false;" style="float: right;" class="brackets tooltip" title="Remove format from the Collector">X</a></span>
                            <br style="clear: all;" />
                        </li>
<?php } /* foreach */ ?>
                    </ul>
                    <select id="formats" style="width: 180px;">
<?php
$OpenGroup = false;
$LastGroupID = -1;
foreach ($ZIPOptions as $Option) {
    list($GroupID, $OptionID, $OptName) = $Option;

    if ($GroupID != $LastGroupID) {
        $LastGroupID = $GroupID;
        if ($OpenGroup) { ?>
                        </optgroup>
<?php   } ?>
                        <optgroup label="<?=$ZIPGroups[$GroupID]?>">
<?php      $OpenGroup = true;
    }
?>
                            <option id="opt<?=$GroupID.$OptionID?>" value="<?=$GroupID.$OptionID?>"<?php if (in_array($GroupID.$OptionID, $ZIPList)) { echo ' disabled="disabled"'; } ?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                        </optgroup>
                    </select>
                    <button type="button" onclick="add_selection()">+</button>
                    <select name="preference" style="width: 210px;">
                        <option value="0"<?php if ($ZIPPrefs == 0) { echo ' selected="selected"'; } ?>>Prefer Original</option>
                        <option value="1"<?php if ($ZIPPrefs == 1) { echo ' selected="selected"'; } ?>>Prefer Best Seeded</option>
                        <option value="2"<?php if ($ZIPPrefs == 2) { echo ' selected="selected"'; } ?>>Prefer Bonus Tracks</option>
                    </select>
                    <input type="submit" style="width: 210px;" value="Download" />
                </form>
            </div>
        </div>
<?php
} /* if (check_perms('zip_downloader')) */ ?>
        <div class="box box_tags">
            <div class="head"><strong>Tags</strong></div>
            <ul class="stats nobullet">
<?php
$artistReleaseType = [];
foreach ($Artist->sections() as $section => $Groups) {
    if (!isset($artistReleaseType[$section])) {
        $artistReleaseType[$section] = 0;
    }
    $artistReleaseType[$section]++;
    foreach ($Groups as $Group) {
        // Skip compilations and soundtracks.
        if ($Group['ReleaseType'] != 7 && $Group['ReleaseType'] != 3) {
            new Tags($Group['TagList'], true);
        }
    }
}
echo Tags::topAsHtml(50, 'torrents.php?taglist=', $name);
Tags::reset();
?>
            </ul>
        </div>
        <div class="box box_info box_statistics_artist">
            <div class="head"><strong>Statistics</strong></div>
            <ul class="stats nobullet">
                <li>Number of groups: <?=number_format($Artist->nrGroups())?></li>
                <li>Number of torrents: <?=number_format($Artist->nrTorrents())?></li>
                <li>Number of seeders: <?=number_format($Artist->nrSeeders())?></li>
                <li>Number of leechers: <?=number_format($Artist->nrLeechers())?></li>
                <li>Number of snatches: <?=number_format($Artist->nrSnatches())?></li>
            </ul>
        </div>
        <div class="box box_info box_metadata_artist">
            <div class="head"><strong>Metadata</strong></div>
            <ul class="stats nobullet">
                <li>Discogs ID: <?= $Artist->discogsId() ?: '<i>not set</i>' ?></li>
<?php if ($Artist->discogsId()) { ?>
                <li>Name: <?= $Artist->discogsName() ?><?= $Artist->discogsIsPreferred()
                    ? '<span title="This artist does not need to display a sequence number for disambiguation">' . " \xE2\x98\x85</span>" : '' ?></li>
                <li><span title="Artists having the same name">Synonyms: <?= $Artist->homonymCount() - 1 ?></span></li>
<?php } ?>
            </ul>
        </div>
        <div class="box box_artists">
            <div class="head"><strong>Similar Artists</strong></div>
            <ul class="stats nobullet">
<?php

if (!$Artist->similarArtists()) { ?>
                <li><span style="font-style: italic;">None found</span></li>
<?php
}
$Max = null;
foreach ($Artist->similarArtists() as $SimilarArtist) {
    list($Artist2ID, $Artist2Name, $Score, $SimilarID) = $SimilarArtist;
    $Score = $Score / 100;
    if (is_null($Max)) {
        $Max = $Score + 1;
    }
    $FontSize = ceil(((($Score - 2) / $Max - 2) * 4)) + 8;
?>
                <li>
                    <span class="tooltip" title="<?=$Score?>"><a href="artist.php?id=<?=$Artist2ID?>" style="float: left; display: block;"><?=$Artist2Name?></a></span>
                    <div style="float: right; display: block; letter-spacing: -1px;">
                        <a href="artist.php?action=vote_similar&amp;artistid=<?=$ArtistID?>&amp;similarid=<?=$SimilarID?>&amp;way=up" class="tooltip brackets vote_artist_up" title="Vote up this similar artist. Use this when you feel that the two artists are quite similar.">&and;</a>
                        <a href="artist.php?action=vote_similar&amp;artistid=<?=$ArtistID?>&amp;similarid=<?=$SimilarID?>&amp;way=down" class="tooltip brackets vote_artist_down" title="Vote down this similar artist. Use this when you feel that the two artists are not all that similar.">&or;</a>
<?php   if (check_perms('site_delete_tag')) { ?>
                        <span class="remove remove_artist"><a href="artist.php?action=delete_similar&amp;artistid=<?=$ArtistID?>&amp;similarid=<?=$SimilarID?>&amp;auth=<?=$LoggedUser['AuthKey']?>" class="tooltip brackets" title="Remove this similar artist">X</a></span>
<?php   } ?>
                    </div>
                    <br style="clear: both;" />
                </li>
<?php
} /* foreach ($Artist->similarArtists()) */ ?>
            </ul>
        </div>
        <div class="box box_addartists box_addartists_similar">
            <div class="head"><strong>Add similar artist</strong></div>
            <ul class="nobullet">
                <li>
                    <form class="add_form" name="similar_artists" action="artist.php" method="post">
                        <input type="hidden" name="action" value="add_similar" />
                        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                        <input type="hidden" name="artistid" value="<?=$ArtistID?>" />
                        <input type="text" autocomplete="off" id="artistsimilar" name="artistname" size="20"<?php Users::has_autocomplete_enabled('other'); ?> />
                        <input type="submit" value="+" />
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="main_column">

<div id="discog_table">
    <div class="box center">
<?php
if ($sections = $Artist->sections()) {
    /* Move the sections to the way the viewer wants to see them. */
    if (isset($LoggedUser['SortHide'])) {
        $reorderedSections = [];
        foreach (array_keys($LoggedUser['SortHide']) as $reltype) {
            if (isset($artistReleaseType[$reltype])) {
                $reorderedSections[$reltype] = $sections[$reltype];
                unset($artistReleaseType[$reltype]);
            }
        }
        /* Any left-over release types */
        foreach (array_keys($artistReleaseType) as $reltype) {
            $reorderedSections[$reltype] = $sections[$reltype];
        }
        $sections = $reorderedSections;
    }

    foreach ($sections as $section => $Groups) {
        $sectionTitle = sectionTitle($section);
        $sectionLabel = strtolower(str_replace(' ', '_', $ReleaseTypes[$section]));
        if (isset($LoggedUser['SortHide'][$section]) && $LoggedUser['SortHide'][$section] == 1) {
            $ToggleStr = " onclick=\"$('.releases_$section').gshow(); return true;\"";
        } else {
            $ToggleStr = '';
        }
?>
        <a href="#torrents_<?= $sectionLabel ?>" class="brackets"<?=$ToggleStr?>><?=$sectionTitle?></a>
<?php
    }

    $Requests = $LoggedUser['DisableRequests'] ? [] : $Artist->requests();
    if (count($Requests)) {
?>
    <a href="#requests" class="brackets">Requests</a>
<?php
    }
?>
    </div>
    <table class="torrent_table grouped release_table m_table">
<?php
    $stylePath = STATIC_SERVER . '/styles/' . $LoggedUser['StyleName'] . '/images/';

    $groupsClosed = ($LoggedUser['TorrentGrouping'] ?? 0) == 1 ? 1 : 0;

    foreach ($sections as $section => $Groups) {
        $sectionLabel = strtolower(str_replace(' ', '_', $ReleaseTypes[$section]));
        $sectionClosed = !isset($LoggedUser['SortHide']) || (array_key_exists($section, $LoggedUser['SortHide']) && $LoggedUser['SortHide'][$section] == 0)
            ? 0 : 1;

        $sectionHidden = $sectionClosed ? ' hidden' : '';
        $groupsHidden = ($sectionClosed || $groupsClosed) ? ' hidden' : '';

?>
                <tr class="colhead_dark" id="torrents_<?= $sectionLabel ?>">
                    <td class="small"><!-- expand/collapse --></td>
                    <td class="m_th_left m_th_left_collapsable" width="70%"><a href="#">&uarr;</a>&nbsp;<strong><?= sectionTitle($section) ?></strong> (<a href="#" onclick="$('.releases_<?= $section ?>').gtoggle(true); return false;">View</a>)</td>
                    <td>Size</td>
                    <td class="sign snatches"><img src="<?= $stylePath ?>snatched.png" class="tooltip" alt="Snatches" title="Snatches" /></td>
                    <td class="sign seeders"><img src="<?= $stylePath ?>seeders.png" class="tooltip" alt="Seeders" title="Seeders" /></td>
                    <td class="sign leechers"><img src="<?= $stylePath ?>leechers.png" class="tooltip" alt="Leechers" title="Leechers" /></td>
                </tr>
<?php
    foreach ($Groups as $Group) {
        $GroupID = $Group['ID'];
        $GroupName = $Group['Name'];
        $GroupYear = $Group['Year'];
        $isSnatched = isset($Group['Flags']) ? $Group['Flags']['IsSnatched'] : false;
        $TorrentTags = new Tags($Group['TagList'], false);
        $Torrents = isset($Group['Torrents']) ? $Group['Torrents'] : [];
        $Artists = $Group['Artists'];
        $ExtendedArtists = $Group['ExtendedArtists'];

        $DisplayName = "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";
        if (check_perms('users_mod') || check_perms('torrents_fix_ghosts')) {
            $DisplayName .= ' <a href="torrents.php?action=fix_group&amp;groupid='.$GroupID.'&amp;artistid='.$ArtistID.'&amp;auth='.$LoggedUser['AuthKey'].'" class="brackets tooltip" title="Fix ghost DB entry">Fix</a>';
        }

        switch ($section) {
            case 1021: // Remixes, DJ Mixes, Guest artists, and Producers need the artist name
            case 1023:
            case 1024:
            case 7:
                if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5]) || !empty($ExtendedArtists[6])) {
                    unset($ExtendedArtists[2], $ExtendedArtists[3]);
                    $DisplayName = Artists::display_artists($ExtendedArtists).$DisplayName;
                } elseif (count($Artists)) {
                    $DisplayName = Artists::display_artists([1 => $Artists], true, true).$DisplayName;
                }
                break;
            case 1022: // Show performers on composer pages
                if (!empty($ExtendedArtists[1]) || !empty($ExtendedArtists[4]) || !empty($ExtendedArtists[5])) {
                    unset($ExtendedArtists[3], $ExtendedArtists[4], $ExtendedArtists[6]);
                    $DisplayName = Artists::display_artists($ExtendedArtists).$DisplayName;
                } elseif (count($Artists)) {
                    $DisplayName = Artists::display_artists([1 => $Artists], true, true).$DisplayName;
                }
                break;
            default: // Show composers otherwise
                if (!empty($ExtendedArtists[4])) {
                    $DisplayName = Artists::display_artists([4 => $ExtendedArtists[4]], true, true).$DisplayName;
                }
        }

        if ($GroupYear > 0) {
            $DisplayName = "$GroupYear - $DisplayName";
        }

        if ($Group['VanityHouse']) {
            $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }
?>
            <tr class="releases_<?=$section?> group groupid_<?=$GroupID?>_header discog<?= $sectionHidden . ($isSnatched ? ' snatched_group' : '') ?>">
                    <td class="td_collapse center m_td_left">
                        <div id="showimg_<?=$GroupID?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                            <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?= $GroupID ?>, this, event);" title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups in this release type."></a>
                        </div>
                    </td>
                    <td colspan="5" class="td_info big_info">
<?php
        if ($LoggedUser['CoverArt']) { ?>
                        <div class="group_image float_left clear">
                            <?php ImageTools::cover_thumb($Group['WikiImage'], $Group['CategoryID']) ?>
                        </div>
<?php
        } ?>
                        <div class="group_info clear">
                            <strong><?=$DisplayName?></strong>
<?php if ($bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID)) { ?>
                            <span class="remove_bookmark float_right">
                                <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                            </span>
<?php
        } else { ?>
                            <span class="add_bookmark float_right">
                                <a style="float: right;" href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                            </span>
<?php
        }

        $UserVotes = Votes::get_user_votes($LoggedUser['ID']);
        $VoteType = isset($UserVotes[$GroupID]['Type']) ? $UserVotes[$GroupID]['Type'] : '';
        Votes::vote_link($GroupID, $VoteType);
?>
                            <div class="tags"><?=$TorrentTags->format('torrents.php?taglist=', $name)?></div>
                        </div>
                    </td>
                </tr>
<?php
        $SnatchedGroupClass = ($Group['Flags']['IsSnatched'] ? ' snatched_group' : '');
        $prevEdition = torrentEdition('', '-', '', '', '');
        $EditionID = 0;
        $UnknownCounter = 0;

        foreach ($Torrents as $TorrentID => $Torrent) {
            $torrentEdition = torrentEdition(
                $Torrent['RemasterTitle'], $Torrent['RemasterYear'], $Torrent['RemasterRecordLabel'],
                $Torrent['RemasterCatalogueNumber'], $Torrent['Media']
            );
            if ($Torrent['Remastered'] && !$Torrent['RemasterYear']) {
                $UnknownCounter++;
            }

            if ($prevEdition != $torrentEdition || $UnknownCounter === 1) {
                $EditionID++;
?>
        <tr class="releases_<?= $section ?> groupid_<?=$GroupID?> edition group_torrent discog<?=$SnatchedGroupClass . $groupsHidden ?>">
            <td colspan="6" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$GroupID?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($Torrent, $Group)?></strong></td>
        </tr>
<?php
            }
            $prevEdition = $torrentEdition;
            $SnatchedTorrentClass = ($Torrent['IsSnatched'] ? ' snatched_torrent' : '');
?>
        <tr class="releases_<?=$section?> torrent_row groupid_<?=$GroupID?> edition_<?=$EditionID?> group_torrent discog<?= $SnatchedTorrentClass . $SnatchedGroupClass . $groupsHidden ?>">
            <td class="td_info" colspan="2">
                <span>
                    [ <a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download"><?=$Torrent['HasFile'] ? 'DL' : 'Missing'?></a>
<?php   if (Torrents::can_use_token($Torrent)) { ?>
                            | <a href="torrents.php?action=download&amp;id=<?=$TorrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($Torrent['Seeders'], $Torrent['Size'])?>');">FL</a>
<?php   } ?>
                            | <a href="ajax.php?action=torrent&amp;id=<?=($TorrentID)?>" download="<?= $name . " - " . $GroupName . ' ['. $GroupYear .']' ?> [<?=($TorrentID)?>] [orpheus.network].json" class="tooltip" title="Download JSON">JS</a>
                    ]
                </span>
                &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?= Torrents::torrent_info($Torrent) ?></a>
            </td>
            <td class="td_size number_column nobr"><?=Format::get_size($Torrent['Size'])?></td>
            <td class="td_snatched number_column m_td_right"><?=number_format($Torrent['Snatched'])?></td>
            <td class="td_seeders number_column<?=(($Torrent['Seeders'] == 0) ? ' r00' : '')?> m_td_right"><?=number_format($Torrent['Seeders'])?></td>
            <td class="td_leechers number_column m_td_right"><?=number_format($Torrent['Leechers'])?></td>
        </tr>
<?php
        } /* torrents */
    } /* group */
    } /* section */
?>
                </table>
            </div>
<?php
} /* all sections */

$Collages = $Cache->get_value("artists_collages_$ArtistID");
if (!is_array($Collages)) {
    $DB->prepared_query("
        SELECT c.Name, c.NumTorrents, c.ID
        FROM collages AS c
        INNER JOIN collages_artists AS ca ON (ca.CollageID = c.ID)
        WHERE CategoryID = '7'
            AND Deleted = '0'
            AND ca.ArtistID = ?
        ", $ArtistID
    );
    $Collages = $DB->to_array();
    $Cache->cache_value("artists_collages_$ArtistID", $Collages, 3600 * 6);
}
if (count($Collages) > 0) {
    if (count($Collages) > MAX_COLLAGES) {
        // Pick some at random
        $Range = range(0,count($Collages) - 1);
        shuffle($Range);
        $Indices = array_slice($Range, 0, MAX_COLLAGES);
        $SeeAll = ' <a href="#" onclick="$(\'.collage_rows\').gtoggle(); return false;">(See all)</a>';
    } else {
        $Indices = range(0, count($Collages)-1);
        $SeeAll = '';
    }
?>
    <table class="collage_table" id="collages">
        <tr class="colhead">
            <td width="85%"><a href="#">&uarr;</a>&nbsp;This artist is in <?=number_format(count($Collages))?> collage<?= plural(count($Collages)) ?><?=$SeeAll?></td>
            <td># artists</td>
        </tr>
<?php
            foreach ($Indices as $i) {
                list($CollageName, $CollageArtists, $CollageID) = $Collages[$i];
                unset($Collages[$i]);
?>
                    <tr>
                        <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                        <td><?=number_format($CollageArtists)?></td>
                    </tr>
<?php
            }
            foreach ($Collages as $Collage) {
                list($CollageName, $CollageArtists, $CollageID) = $Collage;
?>
                    <tr class="collage_rows hidden">
                        <td><a href="collages.php?id=<?=$CollageID?>"><?=$CollageName?></a></td>
                        <td><?=number_format($CollageArtists)?></td>
                    </tr>
<?php       } ?>
    </table>
<?php
}

if ($Requests) {
?>
    <table cellpadding="6" cellspacing="1" border="0" class="request_table border" width="100%" id="requests">
        <tr class="colhead_dark">
            <td style="width: 48%;">
                <a href="#">&uarr;</a>&nbsp;
                <strong>Request Name</strong>
            </td>
            <td class="nobr">
                <strong>Vote</strong>
            </td>
            <td class="nobr">
                <strong>Bounty</strong>
            </td>
            <td>
                <strong>Added</strong>
            </td>
        </tr>
<?php
    $Tags = Requests::get_tags(array_keys($Requests));
    $Row = 'b';
    foreach ($Requests as $RequestID => $Request) {
            $CategoryName = $Categories[$Request['CategoryID'] - 1];
            $Title = display_str($Request['Title']);
            if ($CategoryName == 'Music') {
                $ArtistForm = Requests::get_artists($RequestID);
                $ArtistLink = Artists::display_artists($ArtistForm, true, true);
                $FullName = $ArtistLink."<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">$Title</span> [{$Request['Year']}]</a>";
            } elseif ($CategoryName == 'Audiobooks' || $CategoryName == 'Comedy') {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\"><span dir=\"ltr\">$Title</span> [{$Request['Year']}]</a>";
            } else {
                $FullName = "<a href=\"requests.php?action=view&amp;id=$RequestID\" dir=\"ltr\">$Title</a>";
            }

            if (!empty($Tags[$RequestID])) {
                $ReqTagList = [];
                foreach ($Tags[$RequestID] as $TagID => $TagName) {
                    $ReqTagList[] = "<a href=\"requests.php?tags=$TagName\">".display_str($TagName).'</a>';
                }
                $ReqTagList = implode(', ', $ReqTagList);
            } else {
                $ReqTagList = '';
            }
?>
        <tr class="row<?=($Row === 'b' ? 'a' : 'b')?>">
            <td>
                <?=$FullName?>
                <div class="tags"><?=$ReqTagList?></div>
            </td>
            <td class="nobr">
                <span id="vote_count_<?=$RequestID?>"><?=$Request['Votes']?></span>
<?php       if (check_perms('site_vote')) { ?>
                <input type="hidden" id="auth" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                &nbsp;&nbsp; <a href="javascript:Vote(0, <?=$RequestID?>)" class="brackets"><strong>+</strong></a>
<?php       } ?>
            </td>
            <td class="nobr">
                <span id="bounty_<?=$RequestID?>"><?=Format::get_size($Request['Bounty'])?></span>
            </td>
            <td>
                <?=time_diff($Request['TimeAdded'])?>
            </td>
        </tr>
<?php   } ?>
    </table>
<?php
}

// Similar Artist Map
if ($Artist->similarArtists()) {
    $Similar = new ARTISTS_SIMILAR($ArtistID, $name);
    if ($SimilarData = $Cache->get_value("similar_positions_$ArtistID")) {
        $Similar->load_data($SimilarData);
        if (!(current($Similar->Artists)->NameLength)) {
            unset($Similar);
        }
    }
    if (empty($Similar) || empty($Similar->Artists)) {
        $Img = new IMAGE;
        $Img->create(WIDTH, HEIGHT);
        $Img->color(255, 255, 255, 127);

        $Similar->set_up();
        $Similar->set_positions();
        $Similar->background_image();

        $Cache->cache_value("similar_positions_$ArtistID",  $Similar->dump_data(), 3600 * 24);
    }
?>
        <div id="similar_artist_map" class="box">
            <div id="flipper_head" class="head">
                <a href="#">&uarr;</a>&nbsp;
                <strong id="flipper_title">Similar Artist Map</strong>
                <a id="flip_to" class="brackets" href="#" onclick="flipView(); return false;">Switch to cloud</a>
            </div>
            <div id="flip_view_1" style="display: block; width: <?=(WIDTH)?>px; height: <?=(HEIGHT)?>px; position: relative; background-image: url(<?= STATIC_SERVER ?>/similar/<?=($ArtistID)?>.png?t=<?=(time())?>);">
<?php
    $Similar->write_artists();
?>
            </div>
            <div id="flip_view_2" style="display: none; width: <?=WIDTH?>px; height: <?=HEIGHT?>px;">
                <canvas width="<?=WIDTH?>px" height="<?=(HEIGHT - 20)?>px" id="similarArtistsCanvas"></canvas>
                <div id="artistTags" style="display: none;">
                    <ul><li></li></ul>
                </div>
                <strong style="margin-left: 10px;"><a id="currentArtist" href="#null">Loading...</a></strong>
            </div>
        </div>

<script type="text/javascript">//<![CDATA[
var cloudLoaded = false;

function flipView() {
    var state = document.getElementById('flip_view_1').style.display == 'block';

    if (state) {
        document.getElementById('flip_view_1').style.display = 'none';
        document.getElementById('flip_view_2').style.display = 'block';
        document.getElementById('flipper_title').innerHTML = 'Similar Artist Cloud';
        document.getElementById('flip_to').innerHTML = 'Switch to map';

        if (!cloudLoaded) {
            require("<?= STATIC_SERVER ?>/functions/tagcanvas.js", function () {
                require("<?= STATIC_SERVER ?>/functions/artist_cloud.js", function () {});
            });
            cloudLoaded = true;
        }
    }
    else {
        document.getElementById('flip_view_1').style.display = 'block';
        document.getElementById('flip_view_2').style.display = 'none';
        document.getElementById('flipper_title').innerHTML = 'Similar Artist Map';
        document.getElementById('flip_to').innerHTML = 'Switch to cloud';
    }
}

//TODO move this to global, perhaps it will be used elsewhere in the future
//http://stackoverflow.com/questions/7293344/load-javascript-dynamically
function require(file, callback) {
    var script = document.getElementsByTagName('script')[0],
    newjs = document.createElement('script');

    // IE
    newjs.onreadystatechange = function () {
        if (newjs.readyState === 'loaded' || newjs.readyState === 'complete') {
            newjs.onreadystatechange = null;
            callback();
        }
    };
    // others
    newjs.onload = function () {
        callback();
    };
    newjs.src = file;
    script.parentNode.insertBefore(newjs, script);
}
//]]>
</script>

<?php
} /* if count($Artist->similar()) > 0 */ ?>
        <div id="artist_information" class="box">
            <div id="info" class="head">
                <a href="#">&uarr;</a>&nbsp;
                <strong>Artist Information</strong>
                <a href="#" class="brackets" onclick="$('#body').gtoggle(); return false;">Toggle</a>
            </div>
            <div id="body" class="body"><?=Text::full_format($Artist->body())?></div>
        </div>
<?php
if (defined('LASTFM_API_KEY')) {
    include(__DIR__ . '/concerts.php');
}

[$NumComments, $Page, $Thread, $LastRead] = Comments::load('artist', $ArtistID);
$Pages = Format::get_pages($Page, $NumComments, TORRENT_COMMENTS_PER_PAGE, 9, '#comments');
?>
    <div id="artistcomments">
        <div class="linkbox"><a name="comments"></a>
            <?=($Pages)?>
        </div>
<?php
$comments = new Gazelle\CommentViewer\Artist(G::$Twig, $LoggedUser['ID'], $ArtistID);
$comments->renderThread($Thread, $LastRead ?: 0);
?>
        <div class="linkbox">
            <?=($Pages)?>
        </div>
<?php
    View::parse('generic/reply/quickreply.php', [
        'InputName' => 'pageid',
        'InputID' => $ArtistID,
        'Action' => 'comments.php?page=artist',
        'InputAction' => 'take_post',
        'SubscribeBox' => true
    ]);
?>
        </div>
    </div>
</div>
<?php
View::show_footer();
