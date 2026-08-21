<?php
namespace App\Livewire\Profile;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class Index extends Component {
    public function render() { 
        return view('livewire.profile.index', ['user' => auth()->user()]); 
    }
}
