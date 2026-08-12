import React, { useState } from 'react';
import { getFortune } from '../services/geminiService';
import { Sparkles, MessageCircle } from 'lucide-react';

const FortuneCookie: React.FC = () => {
  const [fortune, setFortune] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [opened, setOpened] = useState(false);

  const crackCookie = async () => {
    if (loading) return;
    setLoading(true);
    setOpened(false);
    const text = await getFortune();
    setFortune(text);
    setLoading(false);
    setOpened(true);
  };

  return (
    <div className="bg-foreground text-white py-16 relative overflow-hidden">
      {/* Pattern Overlay */}
      <div className="absolute inset-0 opacity-5" style={{ backgroundImage: 'radial-gradient(circle, #D4AF37 1px, transparent 1px)', backgroundSize: '30px 30px' }}></div>
      
      <div className="container mx-auto px-4 text-center relative z-10">
        <div className="inline-block p-3 rounded-full bg-secondary/20 mb-6">
          <Sparkles className="w-8 h-8 text-secondary" />
        </div>
        
        <h3 className="text-3xl font-serif font-bold mb-4">Digital Fortune Cookie</h3>
        <p className="text-gray-400 mb-8 max-w-xl mx-auto">
          Finish your digital meal with a touch of wisdom. Let our AI spirits reveal your destiny.
        </p>

        <div className="min-h-[120px] flex flex-col items-center justify-center">
          {!opened && !loading && (
             <button 
               onClick={crackCookie}
               className="group relative px-8 py-3 bg-transparent border-2 border-secondary text-secondary hover:bg-secondary hover:text-foreground transition-all duration-300 uppercase tracking-widest font-bold rounded-full"
             >
               Crack Open a Cookie
             </button>
          )}

          {loading && (
            <div className="flex flex-col items-center animate-pulse">
              <div className="w-12 h-12 border-4 border-secondary border-t-transparent rounded-full animate-spin mb-4"></div>
              <span className="text-secondary tracking-widest">Consulting the oracles...</span>
            </div>
          )}

          {opened && fortune && (
            <div className="animate-fade-in-up max-w-2xl mx-auto bg-white/10 p-6 rounded-lg backdrop-blur-sm border border-white/20">
               <p className="text-xl md:text-2xl font-serif italic text-secondary">"{fortune}"</p>
               <button 
                onClick={crackCookie}
                className="mt-6 text-sm text-gray-400 hover:text-white underline underline-offset-4"
               >
                 Try another
               </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default FortuneCookie;