import React, { useState } from 'react';
import Navbar from './components/Navbar';
import Hero from './components/Hero';
import About from './components/About';
import Menu from './components/Menu';
import Gallery from './components/Gallery';
import ReservationCTA from './components/ReservationCTA';
import Contact from './components/Contact';
import PromotionBanner from './components/PromotionBanner';

const App: React.FC = () => {
  const [showBanner, setShowBanner] = useState(true);

  return (
    <div className="min-h-screen w-full">
      {showBanner && <PromotionBanner onClose={() => setShowBanner(false)} />}
      <Navbar bannerVisible={showBanner} />
      <main>
        <Hero />
        <About />
        <Menu bannerVisible={showBanner} />
        <ReservationCTA />
        <Gallery />
        <Contact />
      </main>
    </div>
  );
};

export default App;