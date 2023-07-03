<?php

namespace MediaWiki\Extension\Umami;

use FormatJson;
use RequestContext;
use TemplateParser;
use SearchResultSet;
use Html;
use MediaWiki\MediaWikiServices;
use Config;

class Hooks {

	/** @var string|null Searched term in Special:Search. */
	public static $searchTerm = null;
	/** @var Config|null Extension Config */
	public static $config=null;
	/** @var string|null Search profile in Special:Search. */
	public static $searchProfile = null;

	/** @var int|null Number of results in Special:Search. */
	public static $searchCount = null;

	/**
	 * Initialize the Umami hook
	 *
	 * @param \OutputPage $out
	 * @param Skin $skin
	 * @return bool
	 */
	public static function UmamiSetup( $out, $skin ) {
		self::$config=MediaWikiServices::getInstance()->getConfigFactory()->makeConfig("Umami");
		$out->addHeadItem( 'umami', self::addUmami( $skin->getTitle() ) );
	}

	/**
	 * Get parameter with the prefix $wgUmami.
	 *
	 * @param string $name Parameter name without any prefix.
	 * @return mixed|null Parameter value.
	 */
	public static function getParameter( $name ) {
		if ( self::$config->has( "Umami$name" ) ) {
			return self::$config->get( "Umami$name" );
		}
		return null;
	}

	/**
	 * Hook to save some data in Special:Search.
	 *
	 * @param string $term Searched term.
	 * @param SearchResultSet|null $titleMatches Results in the titles.
	 * @param SearchResultSet|null $textMatches Results in the fulltext.
	 * @return true
	 */
	public static function onSpecialSearchResults( $term, $titleMatches, $textMatches ) {
		self::$searchTerm = $term;
		self::$searchCount = 0;
		if ( $titleMatches instanceof SearchResultSet ) {
			self::$searchCount += (int)$titleMatches->numRows();
		}
		if ( $textMatches instanceof SearchResultSet ) {
			self::$searchCount += (int)$textMatches->numRows();
		}
		return true;
	}

	/**
	 * Hook to save some data in Special:Search.
	 *
	 * @param SpecialSearch $search Special page.
	 * @param string|null $profile Search profile.
	 * @param SearchEngine $engine Search engine.
	 * @return true
	 */
	public static function onSpecialSearchSetupEngine( $search, $profile, $engine ) {
		self::$searchProfile = $profile;
		return true;
	}

	/**
	 * Add Umami script
	 * @param Title $title
	 * @return string
	 */
	public static function addUmami( $title ) {
		$user = RequestContext::getMain()->getUser();
		if ( $user->isAllowed( 'bot' ) && self::getParameter( 'IgnoreBots' ) ) {
			return '<!-- Umami extension is disabled for bots -->';
		}

		// Ignore Wiki System Operators
		if ( $user->isAllowed( 'protect' ) && self::getParameter( 'IgnoreSysops' ) ) {
			return '<!-- Umami tracking is disabled for users with \'protect\' rights (i.e., sysops) -->';
		}

		// Ignore Wiki Editors
		if ( $user->isAllowed( 'edit' ) && self::getParameter( 'IgnoreEditors' ) ) {
			return "<!-- Umami tracking is disabled for users with 'edit' rights -->";
		}

		$idSite = self::getParameter( 'WebsiteID' );
		$umamiURL = self::getParameter( 'URL' );
		$customJS = self::getParameter( 'CustomJS' );
		$jsFile = self::getParameter( 'JSFile' );
		$hostURL = self::getParameter('HostURL');
		$mwTrack = self::getParameter('MWTrack');
		$dnt=self::getParameter('DNT');
		$cache=self::getParameter('Cache');
		$domains=self::getParameter('Domains');
		$umamiTagArgs = [];
		$umamiTemplateArgs = [];
		$umamiTagArgs['async'] = '';
		$umamiTagArgs["data-auto-track"]="false";
		// Missing configuration parameters
		if ( empty( $idSite ) || empty( $umamiURL ) ) {
			return '<!-- You need to set the settings for Umami -->';
		}
		$templateParser = new TemplateParser(__DIR__ );
		$umamiTagArgs["data-website-id"]=$idSite;
		if($dnt===true){
			$umamiTagArgs['data-do-not-track'] = "true";
		}
		if($cache===true){
			$umamiTagArgs['data-cache'] = "true";
		}
		if($mwTrack===true){
			$umamiTemplateArgs['enableMWTrack']=true;
		}
		if(!empty($domains)){
			$umamiTagArgs['data-domains'] = implode(',',$domains);
		}
		if(!empty($hostURL)){
			$umamiTagArgs['data-host-url'] = implode(',',$domains);
		}
		// Check if we have custom JS
		if ( !empty( $customJS ) ) {
			if ( is_array( $customJS ) ) {
				$customJs = PHP_EOL;
				foreach ( $customJS as $customJsLine ) {
					$customJs .= $customJsLine;
				}
			} else { 
				$customJs = PHP_EOL . $customJS;
			}
		} else { 
			$customJs = null;
		}
		$umamiTemplateArgs['customJS']=$customJS;
		$searchEvent=[];
		if ( self::$searchTerm !== null ) {
			$searchEvent['search'] = self::$searchTerm;
			if ( self::$searchProfile !== null ) {
				$searchEvent['search_cat'] = self::$searchProfile;
			}
			if ( self::$searchCount !== null ) {
				$searchEvent['search_count'] = self::$searchCount ;
			}
			$umamiTemplateArgs['searchEventJson']=FormatJson::encode($searchEvent);
		}
		$umamiTagArgs['src']="$umamiURL/$jsFile";
		$umamiLoad = Html::element('script',$umamiTagArgs);
		$script = $templateParser->processTemplate("Script",$umamiTemplateArgs);
		$umamiScript=Html::rawElement('script',[],$script);
		return $umamiLoad.$umamiScript;
	}

}
