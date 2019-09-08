pipeline {
    agent any 
    stages {
	stage('cleanup') {
	    // Recursively delete all files and folders in the workspace
            // using the built-in pipeline command
            deleteDir()
	}
        stage('build') {
            checkout scm

            sh "composer install"
            sh "cp .env.example .env"
            sh "php artisan key:generate"
        }
        stage('Test') { 
            steps {
                sh 'vendor/bin/phpunit'
            }
        }
    }
}
