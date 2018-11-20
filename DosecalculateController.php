<?php
namespace app\controllers;
use Yii;
use yii\data\ArrayDataProvider;
use yii\web\Controller;

class DosecalculateController extends Controller {
	public function actionPatient($year = null) {

		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
                select year(o.vstdate)+543+if(month(o.vstdate)>9,1,0) year
                ,count(distinct o.hn) as sum
                ,count(distinct if(v.count_in_year=0,p.cid,null)) as newcase
                ,count(distinct o.vn) as krung
                ,sum(sum_price)as sumprice
                from ovst o
                join person p on p.patient_hn=o.hn
                join vn_stat v on o.vn=v.vn
                join opitemrece AS op ON op.vn = o.vn
                where o.vstdate between '2011-10-1' and '2017-09-30'
                group by year
                order by year asc")->queryAll();


		for($i=0;$i<sizeof($data);$i++){

			$newcase[] = $data[$i]['newcase']*1;
			$sumprice[] = $data[$i]['sumprice'];
			$krung[] = $data[$i]['krung']*1;
			$sum[] = $data[$i]['sum']*1;
			$year[] = $data[$i]['year']*1;
		}

		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
		]);
		return $this->render('patientlist',[
				'dataProvider'=>$dataProvider,
				'year'=>$year,
				'sumprice'=>$sumprice,
				'sum'=>$sum,
				'krung'=>$krung,
				'newcase'=>$newcase

		]);
	}

	// จำนวนสิทธฺ์ผู้เข้ารับบริการเอกซเรย์

	public function actionPttypexratlist($date1=null,$date2=null,$total=null,$pttype=null,$name=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}
		$connection = Yii::$app->db2;
		$sql = "SELECT o.pttype as pttype,pt.`name`as name,count(o.pttype)as 'total'
		,count(DISTINCT(v.hn)) as hn,sum(v.income) as income
		from ovst o
		LEFT OUTER JOIN pttype pt on pt.pttype = o.pttype
		LEFT OUTER JOIN vn_stat v on v.vn = o.vn
		WHERE o.vstdate between '$date1' and '$date2'
		AND o.pttype is not NULL
		GROUP BY o.pttype
		ORDER BY total DESC";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['total','name','pttype','hn','income']]
		]);

		return $this->render('pttypexraylist',[
				'dataProvider'=>$dataProvider,
				'sql'=>$sql,
				'rawData'=>$rawData,
				'date1'=>$date1,
				'date2'=>$date2,
				'total'=>$total,
				'pttype'=>$pttype,
				'name'=>$name
		]);
	}

	public function actionPatientlist1($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}

		$connection = Yii::$app->db2;
		$sql= "
		select v.vn,v.hn,pt.cid as cid,CONCAT(pname,fname,' ',lname) ptname,v.vstdate as camedate,v.pdx
		,v.pttype,py.name as pttypename
		,v.income as income,v.rcpt_money as rcpt_money
		,concat(r.rcpno,'#',r.bill_amount) as rcpno
		from ovst o
		join vn_stat v on o.vn=v.vn
		JOIN pttype py on py.pttype = o.pttype
		join patient pt on v.hn=pt.hn
		left join rcpt_print r on r.vn=o.vn
		where v.vstdate between '$date1' and '$date2'
		and (o.an is null or o.an='')
		group by o.vn
		order by pttype";


		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['vn','hn','ptname','income','rcpno','rcpt_money','pttypename','pttype','camedate']]

		]);

		return $this->render('patientlist1',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
				'vn'=>$vn,
				'hn'=>$hn,
				'ptname'=>$ptname,
				'date1'=>$date1,
				'date2'=>$date2,
				'rcpno'=>$rcpno,
		]);
	}


//   	Start  Ptxraylist  = รายชื่อผู้ป่วยที่สั่งเอกซเรย์ ทั้งหมด OPD+IPD

	public function actionPtxraylistall($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}

		$connection = Yii::$app->db2;
		$sql= "SELECT x.order_date_time,x.hn,r.an,p.cid
				,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
				,x.age_y ,x.xray_list,r.clinical_information,r.confirm
				FROM xray_head x
				LEFT JOIN xray_report r ON r.hn = x.hn
				LEFT JOIN patient p ON p.hn = x.hn
				JOIN xray_items i ON i.xray_items_code = r.xray_items_code
				WHERE x.order_date BETWEEN '$date1' and '$date2'
				GROUP BY x.vn
                                ORDER BY x.order_date_time";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['order_date_time','hn','an','cid','ptname','age_y','xray_list','clinical_information','confirm']]

		]);

		return $this->render('ptxraylistall',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
//				'report_date'=>$report_date,
//				'request_time'=>$request_time,
				'hn'=>$hn,
				'ptname'=>$ptname,
//				'age_y'=>$age_y,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

//  	END

//   	Start  Ptxraylist  = รายชื่อผู้ป่วยที่ผู้ช่วยฯ..สิริภาส ใหบริการ ทั้งหมด OPD+IPD

	public function actionPtxraylist_siripas($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}

		$connection = Yii::$app->db2;
		$sql= "SELECT x.order_date_time,x.hn,r.an,p.cid
				,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
				,x.age_y ,x.xray_list,r.clinical_information,r.confirm
				FROM xray_head x
				LEFT JOIN xray_report r ON r.hn = x.hn
				LEFT JOIN patient p ON p.hn = x.hn
				JOIN xray_items i ON i.xray_items_code = r.xray_items_code
				WHERE x.order_date BETWEEN '$date1' and '$date2' and x.doctor_list LIKE '%นายสิริภาส สิริคชารุ่งเรือง'
				GROUP BY x.vn
        ORDER BY x.order_date_time";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['order_date_time','hn','an','cid','ptname','age_y','xray_list','confirm']]

		]);

		return $this->render('ptxraylist_siripas',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
//				'report_date'=>$report_date,
//				'request_time'=>$request_time,
				'hn'=>$hn,
				'ptname'=>$ptname,
//				'age_y'=>$age_y,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

//  	END


//   	Start  Ptxraylist  = รายชื่อผู้ป่วยที่ผู้ช่วยฯ..ไพศาล ให้บริการ ทั้งหมด OPD+IPD

	public function actionPtxraylist_pisan($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}

		$connection = Yii::$app->db2;
		$sql= "SELECT x.order_date_time,x.hn,r.an,p.cid
				,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
				,x.age_y ,x.xray_list,r.clinical_information,r.confirm
				FROM xray_head x
				LEFT JOIN xray_report r ON r.hn = x.hn
				LEFT JOIN patient p ON p.hn = x.hn
				JOIN xray_items i ON i.xray_items_code = r.xray_items_code
				WHERE x.order_date BETWEEN '$date1' and '$date2' and x.doctor_list LIKE '%นายไพศาล แก้วพรวน'
				GROUP BY x.vn
        ORDER BY x.order_date_time";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['order_date_time','hn','an','cid','ptname','age_y','xray_list','confirm']]

		]);

		return $this->render('ptxraylist_pisan',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
//				'report_date'=>$report_date,
//				'request_time'=>$request_time,
				'hn'=>$hn,
				'ptname'=>$ptname,
//				'age_y'=>$age_y,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

//  	END


//   	Start  PtxraylistOPD  = รายชื่อผู้ป่วยที่สั่งเอกซเรย์ OPD
	public function actionPtxraylistopd($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}
		$connection = Yii::$app->db2;
		$sql= "SELECT x.order_date_time,x.hn,p.cid
		,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
		,x.age_y ,x.xray_list,r.clinical_information
		FROM xray_head x
		LEFT JOIN xray_report r ON r.hn = x.hn
		LEFT JOIN patient p ON p.hn = x.hn
		JOIN xray_items i ON i.xray_items_code = r.xray_items_code
		WHERE x.order_date BETWEEN '$date1' and '$date2' AND r.an LIKE ''
		GROUP BY x.vn
                ORDER BY x.order_date_time";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['order_date_time','hn','cid','ptname','age_y','xray_list','clinical_information']]

		]);

		return $this->render('ptxraylistopd',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
				'hn'=>$hn,
				'ptname'=>$ptname,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

	//  	END

// 		Start ค้นหา pt ด้วย HN

	public function actionHnlist($cid=null,$fname=null,$data=null,$hn=null) {

		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
				SELECT hn,cid,pname,fname,lname from patient where hn='$hn' LIMIT 100")->queryAll();

		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
				'pagination'=>FALSE,
				'sort'=>['attributes'=>['hn','cid','fname','lname','pname']]
		]);
		return $this->render('hnlist',[
				'dataProvider'=>$dataProvider,
				'cid'=>$cid,
				'hn'=>$hn,
				'fname'=>$fname,
		]);
	}
// 		End

// 		Start ค้นหา ประวัติการได้รับบริการเอกซเรย์ด้วย HN
	public function actionHnlistdetail($cid=null,$fname=null,$data=null,$hn=null) {
		$connection = Yii::$app->db2;
		$data = $connection->createCommand("SELECT r.request_date,r.examined_time,r.hn,r.an,p.cid
				,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
				,i.xray_items_name,r.clinical_information,r.confirm
				FROM xray_report r
				LEFT JOIN patient p ON p.hn = r.hn
				JOIN xray_items i ON i.xray_items_code = r.xray_items_code
				WHERE r.hn ='$hn'LIMIT 100 ")->queryAll();

		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
				'pagination'=>FALSE,
				'sort'=>['attributes'=>['hn','cid','ptname','xray_items_name','request_date','examined_time']]
		]);
		return $this->render('hnlistdetail',[
				'dataProvider'=>$dataProvider,
				'request_date'=>$request_date,
				'cid'=>$cid,
				'hn'=>$hn,
				'ptname'=>$fname,
		]);
	}

	// 		End


// 		Sum Pttype in X Ray

	public function actionSumpttype($date1=null,$date2=null,$vn=null,$name=null) {
		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
				SELECT pt.hipdata_code,pt.`name`,count(v.vn) vn,count(distinct(v.hn)) hn from xray_head v
				LEFT OUTER JOIN pttype pt on pt.pttype = v.pttype
				LEFT OUTER JOIN xray_report xr ON xr.vn = v.vn
				WHERE xr.report_date BETWEEN '$date1' AND '$date2' GROUP BY pt.hipdata_code")->queryAll();

		for($i=0;$i<sizeof($data);$i++){

			$name[] = $data[$i]['name'];
			$vn[] = $data[$i]['vn']*1;
		}

		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
		]);

		return $this->render('sumpttype',[
				'dataProvider'=>$dataProvider,
				'name'=>$name,
				'vn'=>$vn,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

//  End

// 		Sum IP Plate
	public function actionSumplate($date1=null,$date2=null,$ip=null,$name=null) {
		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
                                SELECT xf.film_name AS name,COUNT(xrf.qty) AS ip
                                FROM xray_report xr
                                LEFT OUTER JOIN xray_report_film xrf ON xrf.xn = xr.xn
                                JOIN xray_film xf ON xf.film_id = xrf.film_id
                                WHERE xr.report_date BETWEEN '$date1' AND '$date2'
                                GROUP BY xf.film_id ")->queryAll();
		for($i=0;$i<sizeof($data);$i++){

			$name[] = $data[$i]['name'];
			$ip[] = $data[$i]['ip']*1;
		}
		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
		]);
		return $this->render('sumplate',[
				'dataProvider'=>$dataProvider,
				'name'=>$name,
				'ip'=>$ip,
				'date1'=>$date1,
				'date2'=>$date2,
		]);
	}

//  End

//  	Start Sum Order
	public function actionSumorder($date1=null,$date2=null,$vn=null,$name=null,$data=null,$hn=null) {
		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
				SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
				LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
				WHERE x.request_date BETWEEN '$date1' AND '$date2' GROUP BY i.xray_items_name")->queryAll();
		for($i=0;$i<sizeof($data);$i++){
			$name[] = $data[$i]['name'];
			$vn[] = [$data[$i]['name'],$data[$i]['vn']*1];
			$hn[] = $data[$i]['hn']*1;
		}
		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
		]);
		return $this->render('sumorder',[
				'dataProvider'=>$dataProvider,
				'name'=>$name,
				'vn'=>$vn,
				'date1'=>$date1,
				'date2'=>$date2,
				'hn'=>$hn,
		]);
	}

// End

//  	Start Sum Order List All (รวมส่วน)

	public function actionSumorderlistall($date1=null,$date2=null,$vn=null,$name=null,$data=null,$hn=null) {

		$connection = Yii::$app->db2;
		$data = $connection->createCommand("
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('104','105','106','107','108','109','110','79','80','81','103')
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('82','83','84','85','112','121')
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code = '19'
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code = '30'
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code = '29'
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code = '9'
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('89','10','11','12')
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('56','57','58','59','60','61','62','63','86','87')
		UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('37','38','39','40','41','42','43','71','74','75','76','77','78',
			'90','91','92','115','116','117','122','123','124')
		UNION
                        SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('2','3','4','5','6','7','8','31','32','33','34','35','36',
			'44','45','52','53','54','55','64','65','93','94','95','96','97','98','99','100','101','102','113','114','125','126','141','142','143','144')
                UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('13','14','15','16','17','18','46','47','48','49','50','51','66',
			'67','68','69','70','72','73','118','119')
		UNION
                        SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('88','127','128','129','130','135','139','140')
                UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code IN ('131','132','133')
                UNION
			SELECT i.xray_items_name as name,COUNT(x.vn) vn,COUNT(DISTINCT (x.vn)) hn  FROM xray_report x
			LEFT OUTER JOIN	xray_items i  ON i.xray_items_code = x.xray_items_code
			WHERE x.request_date BETWEEN '$date1' AND '$date2' AND x.xray_items_code = '134'	")->queryAll();

		for($i=0;$i<sizeof($data);$i++){

			$name[] = $data[$i]['name'];
			$vn[] = [$data[$i]['name'],$data[$i]['vn']*1];
			$hn[] = $data[$i]['hn']*1;
		}

		$dataProvider = new ArrayDataProvider([
				'allModels'=>$data,
		]);

		return $this->render('sumorderlistall',[
				'dataProvider'=>$dataProvider,
				'name'=>$name,
				'vn'=>$vn,
				'date1'=>$date1,
				'date2'=>$date2,
				'hn'=>$hn,
		]);
	}

// End

//  	Start Patient IPD  list

	public function actionPtxrayipdlist($date1=null,$date2=null,$vn=null,$hn=null,$ptname=null,$rcpno=null,$sql=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}

		$connection = Yii::$app->db2;
		$sql= "SELECT x.examined_date,x.examined_time,a.an,a.hn,p.cid
				,CONCAT(p.pname,p.fname,'   ',p.lname) as ptname
				,a.age_y,i.xray_items_name as xrayitem
				FROM an_stat a
				LEFT JOIN patient p ON p.hn = a.hn
				LEFT JOIN xray_report x ON x.an = a.an
				JOIN xray_items i ON i.xray_items_code = x.xray_items_code
				where x.request_date between '$date1' and '$date2'  ";


		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['examined_date','examined_time','an','hn','cid','ptname','age_y','xrayitem']]
		]);

		return $this->render('ptxrayipdlist',[
				'dataProvider'=>$dataProvider,
				'rawData'=>$rawData,
//				'an'=>$an,
				'hn'=>$hn,
				'ptname'=>$ptname,
				'date1'=>$date1,
				'date2'=>$date2,
//				'cid'=>$cid,
				'sql'=>$sql,
		]);
	}
//

//		Start Pttype list OPD

	public function actionPttypexraylist($date1=null,$date2=null,$total=null,$pttype=null,$name=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}
		$connection = Yii::$app->db2;
		$sql = "SELECT x.pttype as pttype ,pt.`name` as name ,COUNT(x.pttype) as total
				,COUNT(DISTINCT(r.hn)) as hn ,SUM(x.xray_price) as income
				FROM xray_head x
				JOIN pttype pt ON pt.pttype = x.pttype
				LEFT OUTER JOIN xray_report r ON r.vn = x.vn
				WHERE r.request_date between '$date1' and '$date2'
				AND x.pttype is not NULL
				GROUP BY x.pttype
				ORDER BY total DESC";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE,
				'sort'=>['attributes'=>['total','name','pttype','hn','income']]
		]);

		return $this->render('pttypexraylist',[
				'dataProvider'=>$dataProvider,
				'sql'=>$sql,
				'rawData'=>$rawData,
				'date1'=>$date1,
				'date2'=>$date2,
				'total'=>$total,
				'pttype'=>$pttype,
				'name'=>$name
		]);

	}
//			END

//			Start Pt Type list IPD
	public function actionPttypelistxrayipd($date1=null,$date2=null,$total=null,$pttype=null,$name=null) {
		if($date1==null){
			$date1=date('Y-m-d');
			$date2=date('Y-m-d');
		}
		$connection = Yii::$app->db2;
		$sql = "SELECT a.pttype as pttype,pt.`name`as name,count(a.pttype)as 'total'
				,count(DISTINCT(a.hn))as hn,sum(a.inc04) as income
				from an_stat a
				JOIN pttype pt on pt.pttype = a.pttype
				LEFT JOIN xray_report x ON x.an = a.an
				WHERE x.request_date between '$date1' and '$date2'
				AND a.pttype is not NULL
				GROUP BY a.pttype
				ORDER BY total DESC";

		try {
			$rawData = \Yii::$app->db2->createCommand($sql)->queryAll();
		} catch (\yii\db\Exception $e) {
			throw new \yii\web\ConflictHttpException('sql error');
		}
		$dataProvider = new \yii\data\ArrayDataProvider([
				'allModels' => $rawData,
				'pagination' =>FALSE
		]);

		return $this->render('pttypelistxrayipd',[
				'dataProvider'=>$dataProvider,
				'sql'=>$sql,
				'rawData'=>$rawData,
				'date1'=>$date1,
				'date2'=>$date2,
				'total'=>$total,
				'pttype'=>$pttype,
				'name'=>$name
		]);
	}

// 			END

}
