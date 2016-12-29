<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;



use Telegram;
use Yajra\Datatables\Html\Builder;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Auth;
use Session;
use App\Kategori;
use App\Tugas;
use App\User;
use File;
use App\KomentarController;
class TugasController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
  
   
   public function index(Request $request, Builder $htmlBuilder)
{
    if ($request->ajax()) {


      $tugas = Tugas::with(['kategori','petugas','menugaskan']);


            return Datatables::of($tugas)->addColumn('action', function($tugas){
                 $id_user = Auth::user()->id;
            return view('tugas._action', 
            [
             'detail_url' => route('tugas.show', $tugas->id),
            'edit_url' => route('tugas.edit', $tugas->id),
             'kerjakan_url' => route('tugas.dikerjakan',$tugas->id),
             'konfirmasi_url' => route('tugas.konfirmasi',$tugas->id),
             'selesai_url' => route('tugas.selesai',$tugas->id),
             'belum_url' => route('tugas.belum',$tugas->id),
            'hapus_url' => route('tugas.destroy',$tugas->id),
            'model' => $tugas,
            'id_user' => $id_user,
            ]);
            })->make(true);
    }
$html = $htmlBuilder
->addColumn(['data' => 'judul', 'name'=>'judul', 'title'=>'Judul Tugas'])
->addColumn(['data' => 'petugas.name', 'name'=>'petugas.name', 'title'=>'Petugas'])
->addColumn(['data' => 'menugaskan.name', 'name'=>'menugaskan.name', 'title'=>'Menugaskan'])
->addColumn(['data' => 'deadline', 'name'=>'deadline', 'title'=>'Deadline'])
->addColumn(['data' => 'created_at', 'name'=>'created_at', 'title'=>'Created At'])
->addColumn(['data' => 'action', 'name'=>'action', 'title'=>'', 'orderable'=>false, 'searchable'=>false]);

return view('tugas.index')->with(compact('html'));
}

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //

 $kategori = Kategori::all()->pluck('nama', 'id');

 $petugas = User::all()->pluck('name', 'id');

 return view('tugas.create',['kategori' => $kategori,'petugas' => $petugas]);


    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //

            $this->validate($request, [
        'judul' => 'required',
        'deskripsi' => 'required',
        'petugas' => 'required',
        'deadline' => 'required:date',
        'kategori_id' => 'required',
        'foto' => 'mimes:jpeg,jpg,png,gif|max:2048'
        ]);

    $id_user = Auth::user()->id;
    $nama_user = Auth::user()->name;

        $tugas =  Tugas::create(['judul' => $request->judul,'deskripsi' => $request->deskripsi,'petugas' => $request->petugas,'menugaskan' =>   $id_user,'deadline' => date('Y-m-d',strtotime($request->deadline)),'kategori_id' => $request->kategori_id]);

        $petugas = User::find($request->petugas);

         $chat_id = -174389666;

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user Menugaskan $petugas->name \n Judul : $request->judul \n Deskripsi : $request->judul  \n Deadline : $request->deadline "
    ]);

         // isi field cover jika ada cover yang diupload
        if ($request->hasFile('foto')) {
        // Mengambil file yang diupload
        $uploaded_foto = $request->file('foto');
        // mengambil extension file
        $extension = $uploaded_foto->getClientOriginalExtension();
        // membuat nama file random berikut extension
        $filename = md5(time()) . '.' . $extension;
        // menyimpan cover ke folder public/img
        $destinationPath = public_path() . DIRECTORY_SEPARATOR . 'img';
        $uploaded_foto->move($destinationPath, $filename);
        // mengisi field cover di book dengan filename yang baru dibuat
        $tugas->foto = $filename;
        $tugas->save();

        $lokasi_foto = $destinationPath."/".$filename;

                 $sendPhoto = Telegram::sendPhoto([
  'chat_id' => $chat_id , 
  'photo' => $lokasi_foto, 
    'caption' =>  $request->judul
]);

        }

         

 
          Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil Membuat Tugas "
    ]);


         return redirect('/tracking/tugas');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //

        $tugas = Tugas::find($id);

        $komentar = KomentarController::with('user')->where('tugas_id',$id)->orderBy('created_at','desc')->get();

        return view('tugas.detail',['tugas' => $tugas ,'komentar' => $komentar]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //


 $kategori = Kategori::all()->pluck('nama', 'id');

 $petugas = User::all()->pluck('name', 'id');
 $tugas = Tugas::find($id);

 return view('tugas.edit',['kategori' => $kategori,'petugas' => $petugas,'tugas'=> $tugas]);


    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //

            $this->validate($request, [
        'judul' => 'required',
        'deskripsi' => 'required',
        'petugas' => 'required',
        'deadline' => 'required',
        'kategori_id' => 'required',
        'foto' => 'image'
        ]);

    $id_user = Auth::user()->id;

         $tugas = Tugas::find($id)->update(['judul' => $request->judul,'deskripsi' => $request->deskripsi,'petugas' => $request->petugas,'menugaskan' =>   $id_user,'deadline' => date('Y-m-d',strtotime($request->deadline)),'kategori_id' => $request->kategori_id]);
$tugas = Tugas::find($id);
          if ($request->hasFile('foto')) {
        // Mengambil file yang diupload
        $uploaded_foto = $request->file('foto');
        // mengambil extension file
        $extension = $uploaded_foto->getClientOriginalExtension();
        // membuat nama file random berikut extension
        $filename = md5(time()) . '.' . $extension;
        // menyimpan cover ke folder public/img
        $destinationPath = public_path() . DIRECTORY_SEPARATOR . 'img';
        $uploaded_foto->move($destinationPath, $filename);


        // hapus cover lama, jika ada
        if ($tugas->foto) {
        $old_foto = $tugas->foto;
        $filepath = public_path() . DIRECTORY_SEPARATOR . 'img'
        . DIRECTORY_SEPARATOR . $tugas->foto;
        try {
        File::delete($filepath);
        } catch (FileNotFoundException $e) {
        // File sudah dihapus/tidak ada
        }
        }
        // mengisi field cover di book dengan filename yang baru dibuat
        $tugas->foto = $filename;
        $tugas->save();
        }

          Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil Mengubah Tugas $request->judul  "
    ]);


         return redirect('/tracking/tugas');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
$tugas = Tugas::find($id);

        Tugas::destroy($id);

                  Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil menghapus Tugas $tugas->judul "
    ]);
    $nama_user = Auth::user()->name;
    $petugas = User::find($tugas->petugas);
     $chat_id = -174389666;

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user Menghapus Tugas yang di berikan kepada $petugas->name \n Judul : $tugas->judul \n Deskripsi : $tugas->judul  \n Deadline : $tugas->deadline "
    ]);


         return redirect('/tracking/tugas');


    }

     public function komentar(Request $request)
    {

            $this->validate($request, [
        'isi_komentar' => 'required',
        'tugas_id' => 'required',
     
        ]);

    $id_user = Auth::user()->id;
    $nama_user =   Auth::user()->name;
    KomentarController::create(['isi_komentar' => $request->isi_komentar,'tugas_id' => $request->tugas_id,'user_id' => $id_user]);

                  Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil mengirim Komentar "
    ]);
        $tugas = Tugas::find($request->tugas_id);

          $chat_id = -174389666;

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user Mengirim Komentar di tugas $tugas->judul : \n $request->isi_komentar "
    ]);

         return redirect('/tracking/tugas/'.$request->tugas_id);


    }
    public function sedang_dikerjakan($id)
    {
        # code...

        $tugas = Tugas::find($id);
          
        $tugas->update(['status_tugas' => 1,'tanggal_dikerjakan' => date('Y-m-d')]);
            Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil mengubah status Tugas menjadi sedang di kerjakan "
    ]);

        $chat_id = -174389666;
 $nama_user = Auth::user()->name;

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user sedang mengerjakan tugas $tugas->judul  "
    ]);

 return redirect('/tracking/tugas');


        
    }

     public function selesai_dikerjakan($id)
    {
        # code...

        $tugas = Tugas::find($id);
        $tugas->update(['status_tugas' => 2,'tanggal_sudah_selesai' => date('Y-m-d')]);
            Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil mengubah status Tugas menjadi selesai di kerjakan "
    ]);
   $chat_id = -174389666;
            $nama_user = Auth::user()->name;

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user selesai mengerjakan tugas $tugas->judul  "
    ]);

 return redirect('/tracking/tugas');


        
    }

     public function konfirmasi_kerjaan($id)
    {
        # code...

        $tugas = Tugas::find($id);
          $id_user = Auth::user()->id;
        $tugas->update(['status_tugas' => 3,'tanggal_dikonfirmasi' => date('Y-m-d'),'pengecek' => $id_user]);
            Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil mengubah status Tugas menjadi Terkonfirmasi "
    ]);

             $chat_id = -174389666;
            $nama_user = Auth::user()->name;
            $petugas = User::find($tugas->petugas);

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user Mengkonfirmasi Bahwa tugas $tugas->judul yang di kerjakan oleh $petugas->name Sudah Benar Diselesaikan  "
    ]);

 return redirect('/tracking/tugas');


        
    }

      public function belum_selesai($id)
    {
        # code...

        $tugas = Tugas::find($id);
       

 return view('tugas.belum',['tugas' => $tugas]);


        
    }

     public function belum_selesai_update(Request $request ,$id)
    {
        # code...

            $this->validate($request, [
        'masalah' => 'required'
        ]);

    $id_user = Auth::user()->id;
        $tugas = Tugas::find($id);

        $tugas->update(['masalah'=> $request->masalah,'status_tugas' => 0]);
  $chat_id = -174389666;
         $nama_user = Auth::user()->name;
            $petugas = User::find($tugas->petugas);

          $response = Telegram::sendMessage([
      'chat_id' =>    $chat_id, 
      'text' => "$nama_user Menyatakan Bahwa tugas $tugas->judul yang di kerjakan oleh $petugas->name BELUM sepenuhnya Selesai dengan alasan berikut : \n $request->masalah   "
    ]);

        if ($request->hasFile('foto_masalah')) {
        // Mengambil file yang diupload
        $uploaded_foto_masalah = $request->file('foto_masalah');
        // mengambil extension file
        $extension = $uploaded_foto_masalah->getClientOriginalExtension();
        // membuat nama file random berikut extension
        $filename = md5(time()) . '.' . $extension;
        // menyimpan cover ke folder public/img
        $destinationPath = public_path() . DIRECTORY_SEPARATOR . 'img';
        $uploaded_foto_masalah->move($destinationPath, $filename);


        // hapus cover lama, jika ada
        if ($tugas->foto_masalah) {
        $old_foto_masalah = $tugas->foto_masalah;
        $filepath = public_path() . DIRECTORY_SEPARATOR . 'img'
        . DIRECTORY_SEPARATOR . $tugas->foto_masalah;
        try {
        File::delete($filepath);
        } catch (FileNotFoundException $e) {
        // File sudah dihapus/tidak ada
        }
        }
        // mengisi field cover di book dengan filename yang baru dibuat
        $tugas->foto_masalah = $filename;
        $tugas->save();


         $lokasi_foto = $destinationPath."/".$filename;

         $sendPhoto = Telegram::sendPhoto([
          'chat_id' => $chat_id , 
          'photo' => $lokasi_foto, 
            'caption' =>  $tugas->judul
        ]);
     }


    Session::flash("flash_notification", [
    "level"=>"success",
    "message"=>"Berhasil mengubah status tugas menjadi belum selesai "
    ]);



 return redirect('/tracking/tugas');


        
    }



}