<?php
/**
 * Simple template loader
 * Loads HTML template files and replaces placeholders
 */

function loadTemplate($templateName, $data = []) {
    $templatePath = __DIR__ . '/../templates/' . $templateName . '.html';
    
    if (!file_exists($templatePath)) {
        return '';
    }
    
    $content = file_get_contents($templatePath);
    
    // Replace placeholders {{key}} with data values
    foreach ($data as $key => $value) {
        $content = str_replace('{{' . $key . '}}', $value, $content);
    }
    
    return $content;
}

function loadModal($modalName) {
    $modalPath = __DIR__ . '/../templates/modals/' . $modalName . '.html';
    
    if (!file_exists($modalPath)) {
        return '';
    }
    
    return file_get_contents($modalPath);
}

function includeTemplate($templateName, $data = []) {
    echo loadTemplate($templateName, $data);
}

function includeModal($modalName) {
    echo loadModal($modalName);
}
