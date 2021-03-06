<?php
authorize();

$id = (int)$_POST['collageid'];
if (!$id) {
    error(404);
}

$collage = new Gazelle\Collage($id);
$isPersonal = $collage->isPersonal();
if (!$isPersonal) {
    if (!check_perms('site_collages_manage')) {
        error(403);
    }
} elseif (!$collage->isOwner($LoggedUser['ID']) && !check_perms('site_collages_delete')) {
    // only owner or mod+ can edit personal collages
    error(403);
}

$collageMan = new Gazelle\Manager\Collage;
if (isset($_POST['name'])) {
    $name = trim($_POST['name']);
    [$ID, $Deleted] = $collageMan->exists($name);
    if ($ID && $ID != $collage->id()) {
        if ($Deleted) {
            $Err = 'A collage with that name already exists but needs to be recovered, please <a href="staffpm.php">contact</a> the staff team!';
        } else {
            $Err = "A collage with that name already exists: <a href=\"/collages.php?id=$ID\">$name</a>.";
        }
        $ErrNoEscape = true;
        require('edit.php');
        exit;
    }
    if ($collage->isOwner($LoggedUser['ID'])) {
        if (!check_perms('site_collages_renamepersonal') && !stristr($name, $LoggedUser['Username'])) {
            error("Your personal collage's title must include your username.");
        }
    }
}

$collage->setUpdate('Description', trim($_POST['description']))
    ->setUpdate('TagList', (new Gazelle\Manager\Tag)->normalize(str_replace(',', ' ', $_POST['tags'])));

if (isset($_POST['featured'])
    && (
        ($collage->isPersonal() && $collage->isOwner($LoggedUser['ID']))
        || check_perms('site_collages_delete')
    )
) {
    $collage->setFeatured();
}

if (($collage->isPersonal() && $collage->isOwner($LoggedUser['ID']) && check_perms('site_collages_renamepersonal'))
    || check_perms('site_collages_delete')
) {
    $collage->setUpdate('Name', trim($_POST['name']));
}

if (isset($_POST['category']) && isset($CollageCats[$_POST['category']]) && (int)$_POST['category'] !== $collage->categoryId()) {
    if ($collage->isPersonal() && !check_perms('site_collages_delete')) {
        error(403);
    }
    $collage->setUpdate('CategoryID', (int)$_POST['category']);
}

if (check_perms('site_collages_delete')) {
    if (isset($_POST['locked']) != $collage->isLocked()) {
        $collage->setToggleLocked();
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroups'] == 0 || is_number($_POST['maxgroups'])) && $_POST['maxgroups'] != $collage->maxGroups()) {
        $collage->setUpdate('MaxGroups', (int)$_POST['maxgroups']);
    }
    if (isset($_POST['maxgroups']) && ($_POST['maxgroupsperuser'] == 0 || is_number($_POST['maxgroupsperuser'])) && $_POST['maxgroupsperuser'] != $collage->maxGroupsPerUser()) {
        $collage->setUpdate('MaxGroupsPerUser', (int)$_POST['maxgroupsperuser']);
    }
}

$collage->modify();
header('Location: collages.php?id=' . $collage->id());
