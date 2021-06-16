<?php

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use Tracy\Debugger;

class HomepagePresenter extends Nette\Application\UI\Presenter
{

    private $database;

    public function __construct(Nette\Database\Context $database)
    {
        $this->database = $database;
    }

    public function actionOverview()
    {
        if(!$this->getUser()->isLoggedIn()) {
            $this->redirect('login');
        }
        if (!$this->isAjax())
        {
            $this->getData();
            $this->template->activeOverview = true;
        }
    }

    public function actionDetails()
    {
        if(!$this->getUser()->isLoggedIn()) {
            $this->redirect('login');
        }
        if (!$this->isAjax())
        {
            $this->getData();
            $this->template->activeDetails = true;
        }
    }

    public function actionReports()
    {
        if(!$this->getUser()->isLoggedIn()) {
            $this->redirect('login');
        }
        if (!$this->isAjax())
        {
            $this->getData();
            $this->template->activeReports = true;
        }
    }

    public function actionXml()
    {
        if(!$this->getUser()->isLoggedIn()) {
            $this->redirect('login');
        }
        if (!$this->isAjax())
        {
            $this->getXmlData();
            $this->template->activeXml = true;
        }
    }

    public function actionLogin()
    {
        if($this->getUser()->isLoggedIn()) {
            $this->redirect('overview');
        }
    }
	// **************************************** Login ****************************************
	protected function createComponentLoginForm()
	{
        $form = new Form;
        
        $form->addText('username')
            ->setHtmlAttribute('placeholder', 'Username')
            ->setRequired('Please enter your username.');

        $form->addPassword('password')
            ->setHtmlAttribute('placeholder', 'Password')
            ->setRequired('Please enter your password');

        $form->addSubmit('submit','Login')
            ->setAttribute('class','btn btn-default bold');

        $form->onSuccess[] = array($this,'loginFormSucceeded');
        
        return $form;
    }
    public function loginFormSucceeded(Form $form, $values)
    {
        try {
            $this->getUser()->login($values['username'], $values['password']);
            $this->getUser()->setExpiration('30 minutes');
            $this->redirect('overview');
    
        } catch (Nette\Security\AuthenticationException $e) {
            $this->flashMessage('Wrong username or password.');
            $this->redrawControl('flashes');
            //$form->addError('Wrong username or password.');
        }
    }
	// **************************************** Filter global ****************************************
	protected function createComponentFilterGlobalForm()
	{
        $form = new Form;
        
        $filter = json_decode($this->getHttpRequest()->getCookie($this->getAction() . 'Filter'),true);

        if (isset($filter['domainFrom'])) {
            $form->addText('domainFrom')
                ->setDefaultValue($filter['domainFrom']);
        } else {
            $form->addText('domainFrom');
        }

        if (isset($filter['spfAlign'])) {
            $form->addSelect('spfAlign',NULL,['pass' => 'pass','fail' => 'fail'])
                ->setPrompt('')
                ->setDefaultValue($filter['spfAlign']);
        } else {
            $form->addSelect('spfAlign',NULL,['pass' => 'pass','fail' => 'fail'])
                ->setPrompt('');
        }        
                
        if (isset($filter['dkimAlign'])) {
            $form->addSelect('dkimAlign',NULL,['pass' => 'pass','fail' => 'fail'])
                ->setPrompt('')
                ->setDefaultValue($filter['dkimAlign']);
        } else {
            $form->addSelect('dkimAlign',NULL,['pass' => 'pass','fail' => 'fail'])
                ->setPrompt('');
        }                    
            
        if (isset($filter['disposition'])) {
            $form->addSelect('disposition',NULL,['none' => 'none','quarantine' => 'quarantine','reject' => 'reject'])
                ->setPrompt('')
                ->setDefaultValue($filter['disposition']);
        } else {
            $form->addSelect('disposition',NULL,['none' => 'none','quarantine' => 'quarantine','reject' => 'reject'])
                ->setPrompt('');
        }                    
            
        if (isset($filter['dateFrom'])) {
            $form->addText('dateFrom')
                ->setDefaultValue($filter['dateFrom']);
        } else {
            $form->addText('dateFrom');
        }        
                
        if (isset($filter['dateTo'])) {
            $form->addText('dateTo')
                ->setDefaultValue($filter['dateTo']);
        } else {
            $form->addText('dateTo');
        }                    

        if (isset($filter['ipAddress'])) {
            $form->addText('ipAddress')
                ->setDefaultValue($filter['ipAddress']);
        } else {
            $form->addText('ipAddress');
        }                    

        $form->addSubmit('submit','Apply')
            ->setAttribute('class','btn btn-info bold');
    
        $form->onSuccess[] = array($this,'filterGlobalFormSucceeded');
        
        return $form;
    }
	public function filterGlobalFormSucceeded(Form $form, $values)
	{
        if (strlen($values['domainFrom']) > 0)
            $filter['domainFrom'] = $values['domainFrom'];
        if (strlen($values['spfAlign']) > 0)
            $filter['spfAlign'] = $values['spfAlign'];
        if (strlen($values['dkimAlign']) > 0)
            $filter['dkimAlign'] = $values['dkimAlign'];
        if (strlen($values['disposition']) > 0)
            $filter['disposition'] = $values['disposition'];
        if (strlen($values['dateFrom']) > 0)
            $filter['dateFrom'] = $values['dateFrom'];
        if (strlen($values['dateTo']) > 0)
            $filter['dateTo'] = $values['dateTo'];
        if (strlen($values['ipAddress']) > 0)
            $filter['ipAddress'] = $values['ipAddress'];
        if(isset($filter)) {
            $this->setFilter($filter);
            $this->getData($filter,1);
            $this->redrawControl($this->getAction() . 'Data');
        } else {
            $this->getData(false,1);
            $this->redrawControl($this->getAction() . 'Data');
        }
    }

    public function handleOpenDetails($domainFrom, $spfAlign, $dkimAlign, $dateFrom, $dateTo)
    {
        $filter['domainFrom'] = $domainFrom;
        $filter['spfAlign'] = $spfAlign;
        $filter['dkimAlign'] = $dkimAlign;
        if (isset($dateFrom))
            $filter['dateFrom'] = $dateFrom;
        if (isset($dateTo))
            $filter['dateTo'] = $dateTo;
        $this->setFilter($filter,'details');
        $this->setPage('details',1);
        $this->redirect('details');
    }

    public function handleOpenReports($domainFrom, $spfAlign, $dkimAlign, $dateFrom, $dateTo, $ipAddress)
    {
        $filter['domainFrom'] = $domainFrom;
        $filter['spfAlign'] = $spfAlign;
        $filter['dkimAlign'] = $dkimAlign;
        if (isset($dateFrom))
            $filter['dateFrom'] = $dateFrom;
        if (isset($dateTo))
            $filter['dateTo'] = $dateTo;
        $filter['ipAddress'] = $ipAddress;
        $this->setFilter($filter,'reports');
        $this->setPage('reports',1);
        $this->redirect('reports');
    }

    public function handleOpenXml($serial)
    {
        $this->setSerial($serial);
        $this->redirect('xml');
    }

    public function handleSetItemsPerPage($pageName, $itemsPerPage)
    {
        $this->setItemsPerPage($pageName, $itemsPerPage);
        $this->getData(NULL,NULL,$itemsPerPage);
        $this->redrawControl($pageName . 'Data');
    }

    public function handleSetPage($pageName, $page)
    {
        $this->setPage($pageName, $page);
        $this->getData(NULL,$page);
        $this->redrawControl($pageName . 'Data');
    }

    public function handleLogout()
    {
        $this->getUser()->logout();
        $this->getHttpResponse()->deleteCookie('overviewFilter');
        $this->getHttpResponse()->deleteCookie('detailsFilter');
        $this->getHttpResponse()->deleteCookie('reportsFilter');
        $this->getHttpResponse()->deleteCookie('pages');
        $this->getHttpResponse()->deleteCookie('itemsPerPages');
        $this->getHttpResponse()->deleteCookie('serial');
        $this->getHttpResponse()->deleteCookie('itemsCounts');
        $this->redirect('login');
    }
	// **************************************** Load data with filter ****************************************
    private function getData($filter = NULL, $page = NULL, $itemsPerPage = NULL)
    {
        if (!isset($page)) {
            $page = $this->getPage($this->getAction());
        } else {
            $this->setPage($this->getAction(),$page);            
        }

        if (!isset($itemsPerPage)) {
            $itemsPerPage = $this->getItemsPerPage($this->getAction());
        }

        if(!isset($filter)) {
            $filter = json_decode($this->getHttpRequest()->getCookie($this->getAction() . 'Filter'),true);
            $itemsCount = $this->getItemsCount($this->getAction());
        } elseif ($filter === false) {
            $this->getHttpResponse()->deleteCookie($this->getAction() . 'Filter');
            $filter = NULL;
        }

        if ($this->getAction() == 'overview') {
            $query = "SELECT sum(rcount) AS count,spf_align,dkim_align,identifier_hfrom FROM rptrecord";
        } elseif ($this->getAction() == 'details') {
            $query = "SELECT sum(rcount) AS count,spf_align,dkim_align,identifier_hfrom,ip,ip6,disposition,dkimdomain,dkimresult,spfdomain,spfresult FROM rptrecord";
        } elseif ($this->getAction() == 'reports') {
            $query = "SELECT sum(rcount) AS count,spf_align,dkim_align,identifier_hfrom,ip,ip6,disposition,dkimdomain,dkimresult,spfdomain,spfresult,rptrecord.serial,mindate,maxdate,org FROM rptrecord";            
            $query .= ' LEFT JOIN report ON rptrecord.serial=report.serial';
        }

        if (isset($filter)) {
            if ($this->getAction() == 'overview' || $this->getAction() == 'details') {
                $query .= ' LEFT JOIN report ON rptrecord.serial=report.serial WHERE';
            } elseif ($this->getAction() == 'reports') {
                $query .= ' WHERE';
            }

            if (isset($filter['domainFrom'])) {
                $queryParameters[] = $filter['domainFrom'];
                $query .= " identifier_hfrom LIKE ? AND";
            }
            if (isset($filter['spfAlign'])) {
                $queryParameters[] = $filter['spfAlign'];
                $query .= " spf_align=? AND";
            }
            if (isset($filter['dkimAlign'])) {
                $queryParameters[] = $filter['dkimAlign'];
                $query .= " dkim_align=? AND";
            }
            if (isset($filter['disposition'])) {
                $queryParameters[] = $filter['disposition'];
                $query .= " disposition=? AND";
            }
            if (isset($filter['dateFrom'])) {
                $this->template->dateFrom = $filter['dateFrom'];
                $dateFrom = explode('.',$filter['dateFrom']);
                $queryParameters[] = implode('/',array($dateFrom[2],$dateFrom[1],$dateFrom[0]));
                $query .= " maxdate>=? AND";
            }
            if (isset($filter['dateTo'])) {
                $this->template->dateTo = $filter['dateTo'];
                $dateTo = explode('.',$filter['dateTo']);
                $queryParameters[] = implode('/',array($dateTo[2],$dateTo[1],$dateTo[0])) . ' 23:59:00';
                $query .= " maxdate<=? AND";
            }
            if (isset($filter['ipAddress'])) {
                $queryParameters[] = ip2long($filter['ipAddress']);
                $queryParameters[] = inet_pton($filter['ipAddress']);
                $query .= " (ip=? OR ip6=?) AND";
            }
            $query = substr($query, 0, -4);
        }

        if ($this->getAction() == 'overview') {
            $query .= " GROUP BY spf_align,dkim_align,identifier_hfrom";
            $query .= " ORDER BY identifier_hfrom,((spf_align='fail') + (dkim_align='fail')),(spf_align='fail')";
        } elseif ($this->getAction() == 'details') {
            $query .= " GROUP BY spf_align,dkim_align,identifier_hfrom,ip,ip6,disposition,dkimdomain,dkimresult,spfdomain,spfresult";
            $query .= " ORDER BY identifier_hfrom,((spf_align='fail') + (dkim_align='fail')),(spf_align='fail'),count DESC,ip,ip6";
        } elseif ($this->getAction() == 'reports') {
            $query .= " GROUP BY spf_align,dkim_align,identifier_hfrom,ip,ip6,disposition,dkimdomain,dkimresult,spfdomain,spfresult,rptrecord.serial,mindate,maxdate,org";
            $query .= " ORDER BY identifier_hfrom,((spf_align='fail') + (dkim_align='fail')),(spf_align='fail'),count DESC,ip,ip6";
        }
        if (!isset($itemsCount)) {
            if (isset($queryParameters)) {
                $itemsCount = $this->getQueryCount($query,$queryParameters);
            } else {
                $itemsCount = $this->getQueryCount($query);    
            }
            $this->setItemsCount($this->getAction(),$itemsCount);
        }
        $paginator = new Nette\Utils\Paginator;
        $paginator->setItemCount($itemsCount);
        $paginator->setItemsPerPage($itemsPerPage);
        $paginator->setPage($page);
        $this->template->paginator = $paginator;
        $this->template->itemsPerPage = $itemsPerPage;
        $query .= " LIMIT ?";
        $queryParameters[] = $paginator->getLength();
        $query .= " OFFSET ?";
        $queryParameters[] = $paginator->getOffset();
        if (isset($queryParameters)) {
            $result = $this->database->query($query,...$queryParameters)->fetchAll();
        } else {
            $result = $this->database->query($query)->fetchAll();    
        }
        if (count($result) != 0) {
            $this->template->data = $result;
        } else {
            $this->template->data = NULL;
        }
    }

	// **************************************** Load xml data ****************************************
    private function getXmlData($serial = NULL)
    {
        if(!isset($serial)) {
            $serial = json_decode($this->getHttpRequest()->getCookie('serial'),true);
        }
        if (isset($serial)) {
            $query = "SELECT raw_xml FROM report WHERE serial=?";
            $result = $this->database->query($query,$serial)->fetchAll();
        }
        if (isset($result) && count($result) != 0) {
            // attempt base64/gzip decode
            $raw_xml = gzdecode(base64_decode($result[0]['raw_xml']));
            if (!$raw_xml) {
                // fall back to raw xml without decoding
                $raw_xml = $result[0]['raw_xml'];
            }
            $this->template->xmlData = $raw_xml;
            $this->template->jsonData = json_encode(simplexml_load_string($raw_xml));
        } else {
            $this->template->xmlData = NULL;
            $this->template->jsonData = NULL;
        }
    }

    private function setFilter($filter, $pageName = NULL)
    {
        if (isset($pageName)) {
            $this->getHttpResponse()->setCookie($pageName . 'Filter',json_encode($filter),0);            
            $this->setItemsCount($pageName,NULL);
        } else {
            $this->getHttpResponse()->setCookie($this->getAction() . 'Filter',json_encode($filter),0);            
            $this->setItemsCount($this->getAction(),NULL);
        }
    }

    private function getItemsPerPage($pageName)
    {
        $itemsPerPages = json_decode($this->getHttpRequest()->getCookie('itemsPerPages'),true);
        if (isset($itemsPerPages[$pageName])) {
            return $itemsPerPages[$pageName];
        } else {
            return 250;
        }
    }

    private function setItemsPerPage($pageName, $itemsPerPage)
    {
        $itemsPerPages = json_decode($this->getHttpRequest()->getCookie('itemsPerPages'),true);
        $itemsPerPages[$pageName] = $itemsPerPage;
        $this->getHttpResponse()->setCookie('itemsPerPages',json_encode($itemsPerPages),0);
    }

    private function getPage($pageName)
    {
        $pages = json_decode($this->getHttpRequest()->getCookie('pages'),true);
        if (isset($pages[$pageName])) {
            return $pages[$pageName];
        } else {
            return 1;
        }
    }

    private function setPage($pageName, $page)
    {
        $pages = json_decode($this->getHttpRequest()->getCookie('pages'),true);
        $pages[$pageName] = $page;
        $this->getHttpResponse()->setCookie('pages',json_encode($pages),0);
    }

    private function getItemsCount($pageName)
    {
        $itemsCounts = json_decode($this->getHttpRequest()->getCookie('itemsCounts'),true);
        if (isset($itemsCounts[$pageName])) {
            return $itemsCounts[$pageName];
        } else {
            return NULL;
        }
    }

    private function setItemsCount($pageName, $itemsCount)
    {
        $itemsCounts = json_decode($this->getHttpRequest()->getCookie('itemsCounts'),true);
        $itemsCounts[$pageName] = $itemsCount;
        $this->getHttpResponse()->setCookie('itemsCounts',json_encode($itemsCounts),0);
    }

    private function setSerial($serial)
    {
        $this->getHttpResponse()->setCookie('serial',json_encode($serial),0);
    }

    private function getQueryCount($query,$queryParameters = NULL)
    {
        $queryCount = 'SELECT count(*) AS count FROM (' . $query . ') temp';
        if (isset($queryParameters)) {
            $result = $this->database->query($queryCount,...$queryParameters)->fetchAll();
        } else {
            $result = $this->database->query($queryCount)->fetchAll();            
        }
        return $result[0]['count'];
    }
}
