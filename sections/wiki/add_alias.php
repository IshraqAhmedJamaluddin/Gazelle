<?php
authorize();

$articleId = (int)$_POST['article'];
if (!$articleId) {
    error(404);
}

$wikiMan = new Gazelle\Manager\Wiki;
if (!$wikiMan->editAllowed($articleId, $LoggedUser['EffectiveClass'])) {
    error(403);
}

try {
    $wikiMan->addAlias($articleId, trim($_POST['alias']), $LoggedUser['ID']);
} catch (DB_MYSQL_DuplicateKeyException $e) {
    error('The alias you attempted to add was either null or already in the database.');
}

header('Location: wiki.php?action=article&id='.$articleId);
